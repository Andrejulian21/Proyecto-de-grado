<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\UserRole;
use App\Exceptions\AiException;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderRegistry;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Providers\NullAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\ProjectDeliveryVersion;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
});

function bindCountingStubProvider(string $json, ?Throwable $failure = null): object
{
    $stub = new class($json, $failure) implements AiProvider
    {
        public int $calls = 0;

        public ?AiRequest $lastRequest = null;

        public function __construct(
            private readonly string $json,
            private readonly ?Throwable $failure,
        ) {}

        public function name(): string
        {
            return 'stub';
        }

        public function complete(AiRequest $request): AiResponse
        {
            $this->calls++;
            $this->lastRequest = $request;

            if ($this->failure !== null) {
                throw $this->failure;
            }

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

function orientationPayload(): string
{
    return json_encode([
        'mensaje' => 'Orientación de prueba.',
        'resumen_conversacion' => 'Resumen.',
        'idea_refinada' => 'Idea refinada.',
        'lineas_investigacion' => ['IA'],
        'tecnologias_recomendadas' => ['Python'],
        'metodologias_sugeridas' => ['SCRUM'],
        'directores_recomendados' => [],
        'riesgos' => ['Ninguno'],
        'proximos_pasos' => ['Definir alcance'],
    ], JSON_THROW_ON_ERROR);
}

it('returns 429 with Retry-After on the 11th chat message within a minute', function () {
    bindCountingStubProvider(orientationPayload());

    for ($i = 1; $i <= 10; $i++) {
        $this->actingAs($this->estudiante)
            ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => "Consulta {$i}"])
            ->assertOk();
    }

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => 'Consulta 11']);

    $response->assertStatus(429)
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('code', 'ai_quota_exceeded')
        ->assertJsonPath('error', 'Límite de cuota de IA alcanzado. Inténtalo de nuevo en 60 segundos.');
});

it('allows 20 chat messages but blocks the 21st for single-use orientation', function () {
    bindCountingStubProvider(orientationPayload());

    for ($i = 1; $i <= 20; $i++) {
        $this->actingAs($this->estudiante)
            ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => "Consulta {$i}"])
            ->assertOk();
        RateLimiter::clear('ai-chat:'.$this->estudiante->id);
    }

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => 'Consulta 21']);

    $response->assertStatus(403)
        ->assertJsonPath('code', 'chat_single_use_exhausted');
});

it('blocks chat for students with an assigned project and director', function () {
    bindCountingStubProvider(orientationPayload());

    $director = User::factory()->create(['role' => UserRole::Director->value]);
    $semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $proyecto = Proyecto::create([
        'title' => 'Proyecto asignado',
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    $proyecto->estudiantes()->attach($this->estudiante);

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => 'Hola']);

    $response->assertStatus(403)
        ->assertJsonPath('code', 'chat_not_eligible');
});

it('returns cached evaluation without calling the provider twice', function () {
    Storage::fake('public');

    $semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $proyecto = Proyecto::create(['title' => 'Proyecto IA', 'semester_id' => $semestre->id]);
    $proyecto->estudiantes()->attach($this->estudiante);

    $entrega = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto',
        'description' => 'Planteamiento del problema.',
        'due_date' => '2026-03-15',
        'status' => 'pendiente',
        'evaluation_metrics' => 'Claridad.',
        'acceptance_criteria' => 'Metodología.',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true, 'analizable_ia' => true],
        ],
    ]);
    $entrega->proyectos()->attach($proyecto->id);

    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Documento de prueba para cache.');
    $relative = 'entregas/'.$entrega->id.'/avance.docx';
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);
    // Bound to the project delivery, exactly as the upload flow does.
    $version = ProjectDeliveryVersion::create($entrega, $proyecto, [
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'avance.docx',
        'file_size' => filesize($absolute) ?: 0,
        'archivo_requerido_id' => 'documento-proyecto',
    ]);

    $payload = json_encode([
        'resumen' => 'Resumen.',
        'coherencia' => 'Coherente.',
        'claridad' => 'Clara.',
        'estructura' => 'Estructurada.',
        'completitud_aparente' => 'Completa.',
        'correspondencia' => 'Corresponde.',
        'observaciones' => ['Obs.'],
        'recomendaciones' => ['Rec.'],
        'conclusion' => 'Conclusión.',
    ], JSON_THROW_ON_ERROR);

    $stub = bindCountingStubProvider($payload);

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ])
        ->assertOk();

    expect($stub->calls)->toBe(1);
});

it('maps provider timeout to 504 with Spanish copy', function () {
    bindCountingStubProvider('{}', AiException::providerTimeout());

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => 'Hola']);

    $response->assertStatus(504)
        ->assertJsonPath('code', 'ai_timeout')
        ->assertJsonPath('error', 'El análisis tardó demasiado. Inténtalo de nuevo.');
});

it('does not consume quota when the AI provider fails', function () {
    bindCountingStubProvider('{}', AiException::providerTimeout());

    for ($i = 1; $i <= 2; $i++) {
        $this->actingAs($this->estudiante)
            ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => "Consulta {$i}"])
            ->assertStatus(504);
    }

    $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/asistente/conversacion')
        ->assertOk()
        ->assertJsonPath('data.mensajes_restantes', 20);
});
