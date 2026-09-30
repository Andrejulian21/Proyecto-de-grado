<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use App\Enums\UserRole;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderRegistry;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Providers\NullAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\PdfDocumentFactory;
use Tests\Support\ProjectDeliveryVersion;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->otro = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto IA',
        'semester_id' => $this->semestre->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->descripcionEsperada = 'En esta entrega el estudiante debe presentar el planteamiento del problema, incluyendo contexto, situación problemática, causas y consecuencias.';

    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto',
        'description' => $this->descripcionEsperada,
        'due_date' => '2026-03-15',
        'status' => 'pendiente',
        'evaluation_metrics' => 'Claridad de objetivos y coherencia metodológica.',
        'acceptance_criteria' => 'Incluir metodología y cronograma.',
        'archivos_requeridos' => [
            [
                'slug' => 'documento-proyecto',
                'nombre' => 'Documento del proyecto',
                'versionamiento' => true,
                'analizable_ia' => true,
            ],
        ],
    ]);
    $this->entrega->proyectos()->attach($this->proyecto->id);
});

function samplePreliminaryPayload(): string
{
    return json_encode([
        'resumen' => 'El documento guarda una relación razonable con lo solicitado.',
        'coherencia' => 'Las secciones se relacionan entre sí de forma general.',
        'claridad' => 'La redacción es comprensible en líneas generales.',
        'estructura' => 'Hay introducción y desarrollo reconocibles.',
        'completitud_aparente' => 'Cubre de manera superficial los temas anunciados.',
        'correspondencia' => 'Aborda el planteamiento del problema pedido en la descripción.',
        'observaciones' => ['Conviene precisar causas y consecuencias.'],
        'recomendaciones' => ['Revisar la sección de contexto con el director.'],
        'conclusion' => 'Análisis preliminar: orientación para mejorar el borrador. No sustituye la revisión del director.',
    ], JSON_THROW_ON_ERROR);
}

/**
 * Versions are bound to the owning project delivery, exactly as the upload
 * flow does. A version with no `entrega_proyecto_id` belongs to no project,
 * so every per-project check would exclude it.
 */
function storeDocxVersion(Entrega $entrega, Proyecto $proyecto, string $name = 'avance.docx'): VersionDocumento
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Documento de prueba para evaluacion inteligente.');
    $section->addText('Objetivo: validar el flujo completo.');

    $relative = 'entregas/'.$entrega->id.'/'.$name;
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    return ProjectDeliveryVersion::create($entrega, $proyecto, [
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => $name,
        'file_size' => filesize($absolute) ?: 0,
        'archivo_requerido_id' => 'documento-proyecto',
    ]);
}

function bindStubAiProvider(string $json): object
{
    $stub = new class($json) implements AiProvider
    {
        public ?AiRequest $lastRequest = null;

        public function __construct(private readonly string $json) {}

        public function name(): string
        {
            return 'stub';
        }

        public function complete(AiRequest $request): AiResponse
        {
            $this->lastRequest = $request;

            return new AiResponse(content: $this->json, provider: $this->name(), model: 'stub-model');
        }
    };

    $registry = new AiProviderRegistry(app(), [
        'stub' => $stub,
        'null' => new NullAiProvider,
    ], 'stub');

    app()->instance(AiProviderRegistry::class, $registry);
    app()->instance(AiGateway::class, new AiGateway($registry));

    return $stub;
}

it('completa analisis preliminar con proveedor stub y persiste observaciones sin calificacion', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);
    $stub = bindStubAiProvider(samplePreliminaryPayload());

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.estado', 'completed')
        ->assertJsonPath('data.resultado.resumen', 'El documento guarda una relación razonable con lo solicitado.')
        ->assertJsonPath('data.resultado.observaciones.0', 'Conviene precisar causas y consecuencias.')
        ->assertJsonPath('data.proveedor', 'stub')
        ->assertJsonMissingPath('data.resultado.puntaje_orientativo');

    expect(AiDocumentEvaluation::query()->count())->toBe(1);
    $row = AiDocumentEvaluation::query()->first();
    expect($row->status)->toBe(AiEvaluationStatus::Completed)
        ->and($row->result_json)->not->toHaveKey('puntaje_orientativo')
        ->and($row->document_hash)->not->toBeNull();

    $promptText = collect($stub->lastRequest?->messages ?? [])
        ->map(fn ($message) => $message->content)
        ->implode("\n");

    expect($promptText)->toContain($this->descripcionEsperada)
        ->and($promptText)->not->toContain('Claridad de objetivos y coherencia metodológica.')
        ->and($promptText)->not->toContain('puntaje_orientativo');
});

it('ignora un puntaje si el proveedor lo incluye en el JSON', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);
    $payload = json_decode(samplePreliminaryPayload(), true, 512, JSON_THROW_ON_ERROR);
    $payload['puntaje_orientativo'] = 95;

    bindStubAiProvider(json_encode($payload, JSON_THROW_ON_ERROR));

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.resultado.puntaje_orientativo');
});

it('responde 503 amigable cuando el proveedor no esta configurado', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(503)
        ->assertJsonPath('code', 'ai_unavailable')
        ->assertJsonPath(
            'error',
            'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.',
        );

    expect(AiDocumentEvaluation::query()->first()?->status)->toBe(AiEvaluationStatus::Failed);
});

it('rechaza versiones cuyo contenido no es DOCX ni PDF', function () {
    $relative = 'entregas/'.$this->entrega->id.'/notas.txt';
    Storage::disk('public')->put($relative, 'texto plano no convertible');

    $version = ProjectDeliveryVersion::create($this->entrega, $this->proyecto, [
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'notas.txt',
        'file_size' => 10,
        'archivo_requerido_id' => 'documento-proyecto',
    ]);

    $stub = bindStubAiProvider(samplePreliminaryPayload());

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'invalid_document')
        ->assertJsonPath('error', 'Solo se aceptan documentos en formato DOCX o PDF.');

    expect($stub->lastRequest)->toBeNull();
});

it('completa analisis preliminar del estudiante a partir de un PDF', function () {
    $relative = 'entregas/'.$this->entrega->id.'/avance.pdf';
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    file_put_contents($absolute, PdfDocumentFactory::bytes('Borrador PDF del estudiante para analisis preliminar.'));

    $version = ProjectDeliveryVersion::create($this->entrega, $this->proyecto, [
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'avance.pdf',
        'file_size' => filesize($absolute) ?: 0,
        'archivo_requerido_id' => 'documento-proyecto',
    ]);

    $stub = bindStubAiProvider(samplePreliminaryPayload());

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'completed');

    $promptText = collect($stub->lastRequest?->messages ?? [])
        ->map(fn ($message) => $message->content)
        ->implode("\n");

    expect($promptText)->toContain('Borrador PDF del estudiante para analisis preliminar.');
});

it('niega acceso a estudiantes de otro proyecto', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);

    $response = $this->actingAs($this->otro)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(404);
});

it('lista de entregas incluye descripcion y no metricas', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $this->entrega->id);

    expect($item['descripcion'])->toBe($this->descripcionEsperada)
        ->and(array_key_exists('metricas_evaluacion', $item))->toBeFalse()
        ->and($item['versiones'][0]['id'])->toBe($version->id);
});

it('analiza archivo temporal sin crear version oficial', function () {
    bindStubAiProvider(samplePreliminaryPayload());

    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Borrador temporal para IA.');
    $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'borrador-ia-'.uniqid().'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);

    $upload = new UploadedFile(
        $tmpPath,
        'borrador.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );

    $versionsBefore = VersionDocumento::query()->count();

    $response = $this->actingAs($this->estudiante)
        ->post("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'file' => $upload,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.temporal', true)
        ->assertJsonPath('data.version_id', null)
        ->assertJsonPath('data.resultado.resumen', 'El documento guarda una relación razonable con lo solicitado.')
        ->assertJsonMissingPath('data.resultado.puntaje_orientativo');

    expect(VersionDocumento::query()->count())->toBe($versionsBefore);
    expect(AiDocumentEvaluation::query()->first()?->version_documento_id)->toBeNull();

    @unlink($tmpPath);
});

it('rechaza el analisis de una version que no es el documento analizable', function () {
    $this->entrega->update([
        'archivos_requeridos' => [
            [
                'slug' => 'documento-proyecto',
                'nombre' => 'Documento del proyecto',
                'versionamiento' => true,
                'analizable_ia' => true,
            ],
            [
                'slug' => 'anexo',
                'nombre' => 'Anexo',
                'versionamiento' => true,
                'analizable_ia' => false,
            ],
        ],
    ]);

    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Anexo no analizable.');
    $relative = 'entregas/'.$this->entrega->id.'/anexo.docx';
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    $version = ProjectDeliveryVersion::create($this->entrega, $this->proyecto, [
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'anexo.docx',
        'file_size' => filesize($absolute) ?: 0,
        'archivo_requerido_id' => 'anexo',
    ]);

    bindStubAiProvider(samplePreliminaryPayload());

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'document_not_analyzable');
    expect(AiDocumentEvaluation::query()->count())->toBe(0);
});

function storeGrupoDocxVersion(Entrega $entrega, Proyecto $proyecto, string $name, string $text): VersionDocumento
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($text);

    $relative = 'entregas/'.$entrega->id.'/'.$name;
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    return ProjectDeliveryVersion::create($entrega, $proyecto, [
        'version_number' => 2,
        'file_path' => $relative,
        'original_name' => $name,
        'file_size' => filesize($absolute) ?: 0,
        'archivo_requerido_id' => 'documento-proyecto',
    ]);
}

it('rechaza con 422 un segundo analisis cuando el grupo ya tiene uno completado', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);
    bindStubAiProvider(samplePreliminaryPayload());

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $otraVersion = storeGrupoDocxVersion($this->entrega, $this->proyecto, 'avance2.docx', 'Segundo documento con contenido distinto del mismo grupo.');

    $companero = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->proyecto->estudiantes()->attach($companero);

    $this->actingAs($companero)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $otraVersion->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ANALISIS_YA_EXISTE')
        ->assertJsonPath('error', 'Ya existe un análisis completado para esta entrega en tu grupo.');

    expect(AiDocumentEvaluation::query()->where('status', AiEvaluationStatus::Completed)->count())->toBe(1);
});

it('permite reintentar el analisis tras un intento fallido', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);

    AiDocumentEvaluation::create([
        'user_id' => $this->estudiante->id,
        'entrega_id' => $this->entrega->id,
        'version_documento_id' => $version->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'type' => AiEvaluationType::PreSubmission,
        'status' => AiEvaluationStatus::Failed,
        'provider' => 'stub',
        'model' => 'stub-model',
        'document_hash' => hash_file('sha256', Storage::disk('public')->path($version->file_path)),
        'prompt_version' => 'v1',
        'error_code' => 'provider_timeout',
        'error_message' => 'El análisis tardó demasiado.',
    ]);

    bindStubAiProvider(samplePreliminaryPayload());

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'completed');

    expect(AiDocumentEvaluation::query()->where('status', AiEvaluationStatus::Completed)->count())->toBe(1);
});

it('permite el analisis a otro proyecto sobre la misma entrega', function () {
    $version = storeDocxVersion($this->entrega, $this->proyecto);
    bindStubAiProvider(samplePreliminaryPayload());

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $otroProyecto = Proyecto::create([
        'title' => 'Otro proyecto IA',
        'semester_id' => $this->semestre->id,
    ]);
    $this->entrega->proyectos()->syncWithoutDetaching([$otroProyecto->id]);

    $estudianteOtro = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $otroProyecto->estudiantes()->attach($estudianteOtro);

    // Each project analyzes its OWN uploaded document: this version belongs to
    // the second project's delivery, which is what allows two independent
    // analyses on a single shared entrega template.
    $otraVersion = storeGrupoDocxVersion($this->entrega, $otroProyecto, 'avance-otro.docx', 'Documento de otro proyecto con contenido propio.');

    $this->actingAs($estudianteOtro)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $otraVersion->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'completed');

    expect(AiDocumentEvaluation::query()->where('status', AiEvaluationStatus::Completed)->count())->toBe(2);
});

it('rechaza el analisis cuando la entrega no tiene documento analizable', function () {
    $this->entrega->update([
        'archivos_requeridos' => [
            [
                'slug' => 'objetivos',
                'nombre' => 'Objetivos',
                'versionamiento' => true,
                'analizable_ia' => false,
            ],
        ],
    ]);

    bindStubAiProvider(samplePreliminaryPayload());

    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Borrador sin documento IA.');
    $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'borrador-sin-ia-'.uniqid().'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);

    $upload = new UploadedFile(
        $tmpPath,
        'borrador.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );

    $response = $this->actingAs($this->estudiante)
        ->post("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'file' => $upload,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'document_not_analyzable');

    @unlink($tmpPath);
});
