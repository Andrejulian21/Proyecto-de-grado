<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\AiEvaluationStatus;
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
use App\Services\Evaluation\DocumentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->otroEstudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->director = User::factory()->create(['role' => UserRole::Director->value]);
    $this->otroDirector = User::factory()->create(['role' => UserRole::Director->value]);

    $this->semestre = Semestre::create([
        'name' => '2026-2',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-15',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto historial IA',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $otroProyecto = Proyecto::create([
        'title' => 'Otro proyecto',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->otroDirector->id,
    ]);
    $otroProyecto->estudiantes()->attach($this->otroEstudiante);

    $this->descripcionEsperada = 'El estudiante debe entregar el marco teórico del proyecto de grado.';

    $this->entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega marco teórico',
        'description' => $this->descripcionEsperada,
        'due_date' => '2026-12-01',
        'status' => 'pendiente',
        'evaluation_metrics' => 'NO debe aparecer en el prompt de IA.',
        'archivos_requeridos' => [
            [
                'slug' => 'marco-teorico',
                'nombre' => 'Marco teórico',
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
    $this->entrega->proyectos()->attach($this->proyecto->id);
});

function historialIaPayload(string $resumen): string
{
    return json_encode([
        'resumen' => $resumen,
        'coherencia' => 'Coherencia preliminar.',
        'claridad' => 'Claridad preliminar.',
        'estructura' => 'Estructura preliminar.',
        'completitud_aparente' => 'Completitud preliminar.',
        'correspondencia' => 'Correspondencia con lo solicitado.',
        'observaciones' => ['Observación preliminar.'],
        'recomendaciones' => ['Recomendación preliminar.'],
        'conclusion' => 'Análisis preliminar. No sustituye al director.',
    ], JSON_THROW_ON_ERROR);
}

function bindHistorialIaStub(string $json): object
{
    $stub = new class($json) implements AiProvider
    {
        public ?AiRequest $lastRequest = null;

        public int $calls = 0;

        public function __construct(public string $json) {}

        public function name(): string
        {
            return 'stub';
        }

        public function complete(AiRequest $request): AiResponse
        {
            $this->lastRequest = $request;
            $this->calls++;

            return new AiResponse(content: $this->json, provider: $this->name(), model: 'stub-model');
        }
    };

    $registry = new AiProviderRegistry(app(), [
        'stub' => $stub,
        'null' => new NullAiProvider,
    ], 'stub');

    app()->instance(AiProviderRegistry::class, $registry);
    app()->forgetInstance(AiGateway::class);
    app()->forgetInstance(DocumentEvaluationService::class);
    app()->instance(AiGateway::class, new AiGateway($registry));

    return $stub;
}

function writeHistorialDocx(string $absolute, string $text): void
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($text);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }

    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);
}

function storeHistorialVersion(
    Entrega $entrega,
    string $slug,
    int $versionNumber,
    string $text,
): VersionDocumento {
    $name = $slug.'_v'.$versionNumber.'.docx';
    $relative = 'entregas/'.$entrega->id.'/'.$slug.'/'.$name;
    $absolute = Storage::disk('public')->path($relative);
    writeHistorialDocx($absolute, $text);

    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => $versionNumber,
        'file_path' => $relative,
        'original_name' => $name,
        'file_size' => filesize($absolute) ?: 0,
        'uploaded_at' => now(),
        'archivo_requerido_id' => $slug,
        'director_notes' => null,
    ]);
}

function makeHistorialUploadedDocx(string $text, string $filename): UploadedFile
{
    $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'historial-ia-'.uniqid().'.docx';
    writeHistorialDocx($tmpPath, $text);

    return new UploadedFile(
        $tmpPath,
        $filename,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );
}

it('persists the schema identity of the requested document on ai evaluations', function () {
    expect(Schema::hasColumn('ai_document_evaluations', 'archivo_requerido_id'))->toBeTrue();
});

it('asocia el analisis al documento y version correctos con fecha', function () {
    $version = storeHistorialVersion($this->entrega, 'marco-teorico', 1, 'Marco teorico v1');
    bindHistorialIaStub(historialIaPayload('Retroalimentación de la versión 1'));

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.documento_id', 'marco-teorico')
        ->assertJsonPath('data.version_id', $version->id)
        ->assertJsonPath('data.temporal', false)
        ->assertJsonPath('data.resultado.resumen', 'Retroalimentación de la versión 1')
        ->assertJsonMissingPath('data.resultado.puntaje_orientativo');

    expect($response->json('data.analizado_en'))->not->toBeNull();

    $row = AiDocumentEvaluation::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->entrega_id)->toBe($this->entrega->id)
        ->and($row->archivo_requerido_id)->toBe('marco-teorico')
        ->and($row->version_documento_id)->toBe($version->id)
        ->and($row->created_at)->not->toBeNull()
        ->and($version->fresh()->director_notes)->toBeNull();
});

it('rechaza analizar un documento no configurado para IA', function () {
    $version = storeHistorialVersion($this->entrega, 'anexo', 1, 'Anexo no IA');
    $stub = bindHistorialIaStub(historialIaPayload('No debería ejecutarse'));

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'document_not_analyzable');

    expect($stub->calls)->toBe(0)
        ->and(AiDocumentEvaluation::query()->where('status', AiEvaluationStatus::Completed)->count())->toBe(0);
});

it('conserva el historial cuando se analiza de nuevo la misma version', function () {
    $version = storeHistorialVersion($this->entrega, 'marco-teorico', 1, 'Marco teorico v1');

    $stub = bindHistorialIaStub(historialIaPayload('Primer análisis'));
    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $primero = AiDocumentEvaluation::query()->first();
    $primerJson = $primero->result_json;
    $primeraFecha = $primero->created_at?->toIso8601String();

    $stub->json = historialIaPayload('Segundo análisis');
    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    expect(AiDocumentEvaluation::query()->where('version_documento_id', $version->id)->count())->toBe(2);

    $primero->refresh();
    expect($primero->result_json)->toBe($primerJson)
        ->and($primero->created_at?->toIso8601String())->toBe($primeraFecha)
        ->and(AiDocumentEvaluation::query()->get()->contains(
            fn (AiDocumentEvaluation $row): bool => ($row->result_json['resumen'] ?? null) === 'Segundo análisis',
        ))->toBeTrue();
});

it('guarda el analisis temporal en el documento IA sin crear version', function () {
    $stub = bindHistorialIaStub(historialIaPayload('Borrador temporal'));
    $upload = makeHistorialUploadedDocx('Borrador del marco teórico', 'borrador.docx');
    $versionsBefore = VersionDocumento::query()->count();

    $this->actingAs($this->estudiante)
        ->post("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'file' => $upload,
        ])
        ->assertOk()
        ->assertJsonPath('data.temporal', true)
        ->assertJsonPath('data.version_id', null)
        ->assertJsonPath('data.documento_id', 'marco-teorico');

    expect(VersionDocumento::query()->count())->toBe($versionsBefore);

    $row = AiDocumentEvaluation::query()->first();
    expect($row->version_documento_id)->toBeNull()
        ->and($row->archivo_requerido_id)->toBe('marco-teorico')
        ->and($row->document_hash)->not->toBeNull();

    $promptText = collect($stub->lastRequest?->messages ?? [])
        ->map(fn ($message) => $message->content)
        ->implode("\n");

    expect($promptText)->toContain($this->descripcionEsperada)
        ->and($promptText)->not->toContain('NO debe aparecer en el prompt de IA.');

    @unlink($upload->getPathname());
});

it('vincula el analisis temporal a la version cuando el hash coincide', function () {
    bindHistorialIaStub(historialIaPayload('Mismo archivo'));
    $text = 'Contenido identico para hash '.uniqid();
    $source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'historial-ia-same-'.uniqid().'.docx';
    writeHistorialDocx($source, $text);
    $copy = $source.'.copy.docx';
    copy($source, $copy);

    $uploadAnalisis = new UploadedFile(
        $source,
        'marco.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );

    $this->actingAs($this->estudiante)
        ->post("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'file' => $uploadAnalisis,
        ])
        ->assertOk();

    $row = AiDocumentEvaluation::query()->first();
    expect($row->version_documento_id)->toBeNull();

    $uploadVersion = new UploadedFile(
        $copy,
        'marco.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );
    $this->actingAs($this->estudiante)
        ->post("/api/entregas/{$this->entrega->id}/archivos/marco-teorico", [
            'file' => $uploadVersion,
        ])
        ->assertCreated();

    $version = VersionDocumento::query()->where('archivo_requerido_id', 'marco-teorico')->first();
    expect($row->fresh()->version_documento_id)->toBe($version->id);

    @unlink($source);
    @unlink($copy);
});

it('no inventa la relacion si el archivo subido no coincide con el analisis temporal', function () {
    bindHistorialIaStub(historialIaPayload('Archivo distinto'));
    $uploadAnalisis = makeHistorialUploadedDocx('Borrador A', 'a.docx');

    $this->actingAs($this->estudiante)
        ->post("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'file' => $uploadAnalisis,
        ])
        ->assertOk();

    $uploadVersion = makeHistorialUploadedDocx('Borrador B distinto', 'b.docx');
    $this->actingAs($this->estudiante)
        ->post("/api/entregas/{$this->entrega->id}/archivos/marco-teorico", [
            'file' => $uploadVersion,
        ])
        ->assertCreated();

    expect(AiDocumentEvaluation::query()->first()->version_documento_id)->toBeNull();

    @unlink($uploadAnalisis->getPathname());
    @unlink($uploadVersion->getPathname());
});

it('el estudiante consulta la retroalimentacion de la version seleccionada', function () {
    $v1 = storeHistorialVersion($this->entrega, 'marco-teorico', 1, 'Version uno');
    $v2 = storeHistorialVersion($this->entrega, 'marco-teorico', 2, 'Version dos');

    $stub = bindHistorialIaStub(historialIaPayload('IA de v1'));
    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $v1->id,
        ])
        ->assertOk();

    $stub->json = historialIaPayload('IA de v2');
    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $v2->id,
        ])
        ->assertOk();

    $this->actingAs($this->estudiante)
        ->getJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente?version_id={$v1->id}")
        ->assertOk()
        ->assertJsonPath('data.version_id', $v1->id)
        ->assertJsonPath('data.documento_id', 'marco-teorico')
        ->assertJsonPath('data.resultado.resumen', 'IA de v1')
        ->assertJsonPath('historial.0.resultado.resumen', 'IA de v1');

    $this->actingAs($this->estudiante)
        ->getJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente?version_id={$v2->id}")
        ->assertOk()
        ->assertJsonPath('data.version_id', $v2->id)
        ->assertJsonPath('data.resultado.resumen', 'IA de v2');
});

it('el director consulta la retroalimentacion IA de la version y no la de otra', function () {
    $v1 = storeHistorialVersion($this->entrega, 'marco-teorico', 1, 'Director v1');
    $v2 = storeHistorialVersion($this->entrega, 'marco-teorico', 2, 'Director v2');

    $stub = bindHistorialIaStub(historialIaPayload('Director IA v1'));
    $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $v1->id,
        ])
        ->assertOk();

    $stub->json = historialIaPayload('Director IA v2');
    $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $v2->id,
        ])
        ->assertOk();

    $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?version_id={$v1->id}")
        ->assertOk()
        ->assertJsonPath('data.version_id', $v1->id)
        ->assertJsonPath('data.documento_id', 'marco-teorico')
        ->assertJsonPath('data.resultado.resumen', 'Director IA v1')
        ->assertJsonMissingPath('data.resultado.puntaje_orientativo');

    $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?version_id={$v2->id}")
        ->assertOk()
        ->assertJsonPath('data.resultado.resumen', 'Director IA v2');
});

it('el detalle de entrega separa observacion del director y retroalimentacion IA', function () {
    $version = storeHistorialVersion($this->entrega, 'marco-teorico', 1, 'Documento con ambos');
    $version->update(['director_notes' => 'Es necesario fortalecer el marco teórico.']);

    bindHistorialIaStub(historialIaPayload('El documento presenta una estructura coherente.'));
    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $asEstudiante = $this->actingAs($this->estudiante)
        ->getJson("/api/admin/entregas/{$this->entrega->id}")
        ->assertOk();

    $versiones = collect($asEstudiante->json('data.versiones'));
    $vista = $versiones->firstWhere('id', $version->id);

    expect($vista['director_notes'])->toBe('Es necesario fortalecer el marco teórico.')
        ->and($vista['analisis_ia'])->toBeArray()
        ->and($vista['analisis_ia'][0]['resultado']['resumen'])->toBe('El documento presenta una estructura coherente.')
        ->and($vista['analisis_ia'][0]['analizado_en'])->not->toBeNull()
        ->and($vista['analisis_ia'][0]['documento_id'])->toBe('marco-teorico');

    $anexo = storeHistorialVersion($this->entrega, 'anexo', 1, 'Anexo sin IA');
    $asDirector = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}")
        ->assertOk();

    $anexoVista = collect($asDirector->json('data.versiones'))->firstWhere('id', $anexo->id);
    expect($anexoVista['analisis_ia'] ?? [])->toBeEmpty();
});

it('niega consultar o analizar a usuarios de otro proyecto', function () {
    $version = storeHistorialVersion($this->entrega, 'marco-teorico', 1, 'Privado');

    $this->actingAs($this->otroEstudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertForbidden();

    $this->actingAs($this->otroEstudiante)
        ->getJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente?version_id={$version->id}")
        ->assertForbidden();

    $this->actingAs($this->otroDirector)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?version_id={$version->id}")
        ->assertForbidden();
});
