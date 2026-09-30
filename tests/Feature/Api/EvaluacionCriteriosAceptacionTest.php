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
use App\Services\Evaluation\DocumentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\ProjectDeliveryVersion;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake();
    Storage::fake('public');
    Storage::fake('local');

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->director = User::factory()->create(['role' => UserRole::Director->value]);

    $this->semestre = Semestre::create([
        'name' => '2026-2',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-15',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto criterios IA',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);
});

function criteriosEntrega(?string $criteria, Semestre $semestre, Proyecto $proyecto): Entrega
{
    $entrega = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega con criterios',
        'description' => 'El estudiante debe entregar el planteamiento del problema.',
        'acceptance_criteria' => $criteria,
        'due_date' => '2026-12-01',
        'status' => 'pendiente',
        'archivos_requeridos' => [
            [
                'slug' => 'planteamiento',
                'nombre' => 'Planteamiento',
                'versionamiento' => true,
                'analizable_ia' => true,
            ],
        ],
    ]);
    $entrega->proyectos()->attach($proyecto->id);

    return $entrega;
}

function criteriosPayload(string $resumen): string
{
    return json_encode([
        'resumen' => $resumen,
        'coherencia' => 'Coherencia preliminar.',
        'claridad' => 'Claridad preliminar.',
        'estructura' => 'Estructura preliminar.',
        'completitud_aparente' => 'Completitud preliminar.',
        'correspondencia' => 'Correspondencia criterio por criterio.',
        'observaciones' => ['Observación preliminar.'],
        'recomendaciones' => ['Recomendación preliminar.'],
        'conclusion' => 'Análisis preliminar. No sustituye al director.',
    ], JSON_THROW_ON_ERROR);
}

function bindCriteriosStub(string $json): object
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

/**
 * Versions are bound to the owning project delivery, exactly as the upload
 * flow does. Without `entrega_proyecto_id` a version belongs to no project
 * and every per-project check excludes it.
 */
function storeCriteriosVersion(Entrega $entrega, Proyecto $proyecto, string $text): VersionDocumento
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($text);

    $relative = 'entregas/'.$entrega->id.'/planteamiento/planteamiento_v1.docx';
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }

    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    return ProjectDeliveryVersion::create($entrega, $proyecto, [
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'planteamiento_v1.docx',
        'file_size' => filesize($absolute) ?: 0,
        'archivo_requerido_id' => 'planteamiento',
        'director_notes' => null,
    ]);
}

function criteriosPromptText(object $stub): string
{
    return collect($stub->lastRequest?->messages ?? [])
        ->map(fn ($message) => $message->content)
        ->implode("\n");
}

it('envía los criterios de aceptación en el prompt y guarda la versión v2', function () {
    $criterios = "1. El documento incluye el planteamiento del problema.\n2. El documento incluye los objetivos.";
    $entrega = criteriosEntrega($criterios, $this->semestre, $this->proyecto);
    $version = storeCriteriosVersion($entrega, $this->proyecto, 'Planteamiento con criterios');
    $stub = bindCriteriosStub(criteriosPayload('Análisis con criterios'));

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.resultado.resumen', 'Análisis con criterios');

    $promptText = criteriosPromptText($stub);

    expect($promptText)->toContain('El documento incluye el planteamiento del problema.')
        ->and($promptText)->toContain('El documento incluye los objetivos.')
        ->and($promptText)->toContain('Criterios de aceptación');

    $row = AiDocumentEvaluation::query()->first();
    expect($row->prompt_version)->toBe('preliminary_analysis_v2');
});

it('usa el texto de respaldo cuando la entrega no define criterios', function () {
    $entrega = criteriosEntrega(null, $this->semestre, $this->proyecto);
    $version = storeCriteriosVersion($entrega, $this->proyecto, 'Planteamiento sin criterios');
    $stub = bindCriteriosStub(criteriosPayload('Análisis sin criterios'));

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    expect(criteriosPromptText($stub))->toContain('No se definieron criterios de aceptación');
});

it('no permite un segundo analisis aunque cambie la version del prompt', function () {
    $entrega = criteriosEntrega('1. El documento incluye el planteamiento del problema.', $this->semestre, $this->proyecto);
    $version = storeCriteriosVersion($entrega, $this->proyecto, 'Planteamiento caché v1');
    $model = (string) config('ai.gemini.model', 'gemini-2.0-flash');
    $hash = hash_file('sha256', Storage::disk('public')->path($version->file_path));

    AiDocumentEvaluation::create([
        'user_id' => $this->estudiante->id,
        'entrega_id' => $entrega->id,
        'version_documento_id' => $version->id,
        'archivo_requerido_id' => 'planteamiento',
        'type' => AiEvaluationType::PreSubmission,
        'status' => AiEvaluationStatus::Completed,
        'provider' => 'stub',
        'model' => $model,
        'document_hash' => $hash,
        'prompt_version' => 'preliminary_analysis_v1',
        'result_json' => ['resumen' => 'Análisis viejo v1'],
    ]);

    $stub = bindCriteriosStub(criteriosPayload('Análisis nuevo v2'));

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ANALISIS_YA_EXISTE');

    expect($stub->calls)->toBe(0)
        ->and(AiDocumentEvaluation::query()->count())->toBe(1);
});
