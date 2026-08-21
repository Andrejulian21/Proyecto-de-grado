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
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->director = User::factory()->create(['role' => UserRole::Director->value]);
    $this->otroDirector = User::factory()->create(['role' => UserRole::Director->value]);
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto ABET',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->descripcionEsperada = 'En esta entrega el estudiante debe presentar el planteamiento del problema.';

    $this->entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto ABET',
        'description' => $this->descripcionEsperada,
        'due_date' => '2026-03-15',
        'status' => 'enviada',
        'evaluation_metrics' => 'Claridad de objetivos y evidencia de competencias.',
        'acceptance_criteria' => 'Incluir metodología y resultados preliminares.',
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

function storeAbetDocxVersion(Entrega $entrega, string $name = 'avance.docx'): VersionDocumento
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Documento de prueba para analisis preliminar del director.');
    $section->addText('Objetivo: validar el flujo sin metricas.');

    $relative = 'entregas/'.$entrega->id.'/'.$name;
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => $name,
        'file_size' => filesize($absolute) ?: 0,
        'uploaded_at' => now(),
        'archivo_requerido_id' => 'documento-proyecto',
    ]);
}

function bindAbetStubProvider(string $json): object
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

function sampleDirectorPreliminaryPayload(): string
{
    return json_encode([
        'resumen' => 'Análisis preliminar del documento oficial.',
        'coherencia' => 'El hilo argumental es reconocible.',
        'claridad' => 'La redacción se entiende en términos generales.',
        'estructura' => 'Hay secciones identificables.',
        'completitud_aparente' => 'Cubre de forma superficial lo pedido.',
        'correspondencia' => 'Se relaciona con el planteamiento del problema.',
        'observaciones' => ['Falta detalle en las consecuencias.'],
        'recomendaciones' => ['Pedir al estudiante que amplíe el contexto.'],
        'conclusion' => 'Orientación preliminar. La evaluación académica corresponde al director.',
    ], JSON_THROW_ON_ERROR);
}

it('completa analisis preliminar del director con proveedor stub', function () {
    $version = storeAbetDocxVersion($this->entrega);
    $stub = bindAbetStubProvider(sampleDirectorPreliminaryPayload());

    $response = $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.tipo', 'abet')
        ->assertJsonPath('data.estado', 'completed')
        ->assertJsonPath('data.proveedor', 'stub')
        ->assertJsonPath('data.resultado.observaciones.0', 'Falta detalle en las consecuencias.')
        ->assertJsonMissingPath('data.perfil_metricas')
        ->assertJsonMissingPath('data.resultado.criterios_evaluados')
        ->assertJsonMissingPath('data.resultado.puntaje_orientativo');

    $row = AiDocumentEvaluation::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->type)->toBe(AiEvaluationType::Abet)
        ->and($row->status)->toBe(AiEvaluationStatus::Completed)
        ->and($row->result_json)->not->toHaveKey('perfil_metricas');

    $promptText = collect($stub->lastRequest?->messages ?? [])
        ->map(fn ($message) => $message->content)
        ->implode("\n");

    expect($promptText)->toContain($this->descripcionEsperada)
        ->and($promptText)->not->toContain('Claridad de objetivos y evidencia de competencias.')
        ->and($promptText)->not->toContain('abet_placeholder_v1');
});

it('responde 503 amigable cuando el proveedor no esta configurado', function () {
    $version = storeAbetDocxVersion($this->entrega);

    $response = $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
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

it('niega acceso a un director que no dirige el proyecto', function () {
    $version = storeAbetDocxVersion($this->entrega);

    $response = $this->actingAs($this->otroDirector)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(403);
});

it('devuelve el ultimo analisis preliminar completado', function () {
    $version = storeAbetDocxVersion($this->entrega);
    bindAbetStubProvider(sampleDirectorPreliminaryPayload());

    $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet");

    $response->assertOk()
        ->assertJsonPath('data.tipo', 'abet')
        ->assertJsonPath('data.resultado.conclusion', 'Orientación preliminar. La evaluación académica corresponde al director.')
        ->assertJsonMissingPath('data.perfil_metricas');
});

it('exige autenticacion', function () {
    $this->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
        'version_id' => 1,
    ])->assertUnauthorized();
});

it('rechaza el analisis del director sobre un documento no analizable', function () {
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

    $version = VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'anexo.docx',
        'file_size' => filesize($absolute) ?: 0,
        'uploaded_at' => now(),
        'archivo_requerido_id' => 'anexo',
    ]);

    bindAbetStubProvider(sampleDirectorPreliminaryPayload());

    $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'document_not_analyzable');
});
