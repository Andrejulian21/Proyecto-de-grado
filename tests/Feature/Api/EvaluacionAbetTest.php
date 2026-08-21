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

    $this->entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto ABET',
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
    $section->addText('Documento de prueba para evaluacion ABET.');
    $section->addText('Objetivo: validar el flujo de evaluacion por criterios.');

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

function bindAbetStubProvider(string $json): void
{
    $stub = new class($json) implements AiProvider
    {
        public function __construct(private readonly string $json) {}

        public function name(): string
        {
            return 'stub';
        }

        public function complete(AiRequest $request): AiResponse
        {
            return new AiResponse(content: $this->json, provider: $this->name(), model: 'stub-model');
        }
    };

    $registry = new AiProviderRegistry(app(), [
        'stub' => $stub,
        'null' => new NullAiProvider,
    ], 'stub');

    app()->instance(AiProviderRegistry::class, $registry);
    app()->instance(AiGateway::class, new AiGateway($registry));
}

function sampleAbetPayload(): string
{
    return json_encode([
        'resumen_ejecutivo' => 'El documento muestra avances parciales en competencias clave.',
        'criterios_evaluados' => [
            [
                'id' => 'SO1',
                'nombre' => 'Resolución de problemas complejos',
                'cumplimiento' => 'medio',
                'evidencias' => ['Se formula el problema en la introducción'],
                'observaciones' => 'Falta mayor formalización matemática',
            ],
        ],
        'fortalezas' => ['Estructura clara'],
        'oportunidades_mejora' => ['Ampliar evidencia de experimentación'],
        'observaciones' => ['El alcance aún es amplio'],
        'recomendaciones' => ['Agregar métricas de éxito'],
        'riesgos' => ['Retraso por alcance'],
        'conclusion' => 'Apto para revisión del Director con ajustes menores.',
        'perfil_metricas' => 'abet_placeholder_v1',
    ], JSON_THROW_ON_ERROR);
}

it('completa evaluacion ABET con proveedor stub y persiste resultado', function () {
    $version = storeAbetDocxVersion($this->entrega);
    bindAbetStubProvider(sampleAbetPayload());

    $response = $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.tipo', 'abet')
        ->assertJsonPath('data.estado', 'completed')
        ->assertJsonPath('data.proveedor', 'stub')
        ->assertJsonPath('data.perfil_metricas', 'abet_placeholder_v1')
        ->assertJsonPath('data.resultado.resumen_ejecutivo', 'El documento muestra avances parciales en competencias clave.')
        ->assertJsonPath('data.resultado.criterios_evaluados.0.id', 'SO1');

    $row = AiDocumentEvaluation::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->type)->toBe(AiEvaluationType::Abet)
        ->and($row->status)->toBe(AiEvaluationStatus::Completed)
        ->and($row->result_json['perfil_metricas'])->toBe('abet_placeholder_v1');
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

it('devuelve la ultima evaluacion ABET completada', function () {
    $version = storeAbetDocxVersion($this->entrega);
    bindAbetStubProvider(sampleAbetPayload());

    $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet");

    $response->assertOk()
        ->assertJsonPath('data.tipo', 'abet')
        ->assertJsonPath('data.resultado.conclusion', 'Apto para revisión del Director con ajustes menores.');
});

it('exige autenticacion', function () {
    $this->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
        'version_id' => 1,
    ])->assertUnauthorized();
});
