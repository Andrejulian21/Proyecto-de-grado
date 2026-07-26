<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\AiAssistantStatus;
use App\Enums\UserRole;
use App\Models\AiAssistantConversation;
use App\Models\AiAssistantMessage;
use App\Models\DirectorAcademicProfile;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderRegistry;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Providers\NullAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->director = User::factory()->create([
        'role' => UserRole::Director->value,
        'name' => 'Ana Directora',
        'max_capacity' => 3,
        'areas' => "Inteligencia Artificial\nDatos",
    ]);

    DirectorAcademicProfile::create([
        'user_id' => $this->director->id,
        'research_lines' => ['Inteligencia Artificial', 'Analítica de datos'],
        'technologies' => ['Python', 'PostgreSQL'],
        'methodologies' => ['SCRUM'],
        'academic_experience' => 'Dirección de proyectos de IA aplicada.',
    ]);

    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Asistente académico PG',
        'semester_id' => $this->semestre->id,
        'code' => 'PG-AA01',
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);
});

function bindAssistantStubProvider(string $json): void
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

function sampleAssistantPayload(int $directorId, ?int $hallucinatedId = null): string
{
    $directors = [
        [
            'id' => $directorId,
            'nombre' => 'Ana Directora',
            'justificacion' => 'Afinidad con IA y disponibilidad de cupo.',
            'afinidad' => 0.91,
        ],
    ];

    if ($hallucinatedId !== null) {
        $directors[] = [
            'id' => $hallucinatedId,
            'nombre' => 'Director Fantasma',
            'justificacion' => 'No existe en el catálogo.',
            'afinidad' => 0.5,
        ];
    }

    return json_encode([
        'mensaje' => 'Te ayudo a refinar tu idea y te sugiero un Director.',
        'resumen_conversacion' => 'El estudiante explora un proyecto de IA educativa.',
        'idea_refinada' => 'Sistema de apoyo académico con IA para proyectos de grado.',
        'lineas_investigacion' => ['IA aplicada a educación'],
        'tecnologias_recomendadas' => ['Python', 'Laravel'],
        'metodologias_sugeridas' => ['Design Science Research'],
        'directores_recomendados' => $directors,
        'riesgos' => ['Alcance demasiado amplio'],
        'proximos_pasos' => ['Definir stakeholders y métricas de éxito'],
    ], JSON_THROW_ON_ERROR);
}

it('obtiene o crea conversacion vacia del asistente', function () {
    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/asistente/conversacion');

    $response->assertOk()
        ->assertJsonPath('data.tipo', 'student_orientation')
        ->assertJsonPath('data.mensajes', []);

    expect(AiAssistantConversation::query()->count())->toBe(1);
});

it('completa un turno con proveedor stub y persiste mensajes', function () {
    bindAssistantStubProvider(sampleAssistantPayload($this->director->id));

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', [
            'mensaje' => 'Quiero un proyecto de IA para apoyar entregas académicas.',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.estado', 'completed')
        ->assertJsonPath('data.proveedor', 'stub')
        ->assertJsonPath('data.resultado.idea_refinada', 'Sistema de apoyo académico con IA para proyectos de grado.')
        ->assertJsonPath('data.resultado.directores_recomendados.0.id', $this->director->id)
        ->assertJsonPath('data.mensaje_asistente.content', 'Te ayudo a refinar tu idea y te sugiero un Director.');

    expect(AiAssistantMessage::query()->count())->toBe(2);
    expect(AiAssistantConversation::query()->first()?->status)->toBe(AiAssistantStatus::Completed);
});

it('responde 503 amigable cuando el proveedor no esta configurado', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', [
            'mensaje' => 'Necesito orientación para mi proyecto.',
        ]);

    $response->assertStatus(503)
        ->assertJsonPath('code', 'ai_unavailable')
        ->assertJsonPath(
            'error',
            'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.',
        );

    expect(AiAssistantConversation::query()->first()?->status)->toBe(AiAssistantStatus::Failed);
    // User message is persisted; no fabricated assistant reply.
    expect(AiAssistantMessage::query()->where('role', 'assistant')->count())->toBe(0);
    expect(AiAssistantMessage::query()->where('role', 'user')->count())->toBe(1);
});

it('descarta directores recomendados que no existen en el catalogo', function () {
    bindAssistantStubProvider(sampleAssistantPayload($this->director->id, hallucinatedId: 999999));

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', [
            'mensaje' => 'Recomiéndame directores para un proyecto de IA.',
        ]);

    $response->assertOk();
    $directores = $response->json('data.resultado.directores_recomendados');

    expect($directores)->toHaveCount(1)
        ->and($directores[0]['id'])->toBe($this->director->id);
});

it('mantiene continuidad del historial en get conversacion', function () {
    bindAssistantStubProvider(sampleAssistantPayload($this->director->id));

    $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', [
            'mensaje' => 'Primera consulta sobre mi idea.',
        ])
        ->assertOk();

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/asistente/conversacion');

    $response->assertOk();
    expect($response->json('data.mensajes'))->toHaveCount(2)
        ->and($response->json('data.resultado.resumen_conversacion'))
        ->toBe('El estudiante explora un proyecto de IA educativa.');
});

it('exige autenticacion', function () {
    $this->getJson('/api/estudiante/asistente/conversacion')->assertUnauthorized();
    $this->postJson('/api/estudiante/asistente/mensajes', [
        'mensaje' => 'Hola',
    ])->assertUnauthorized();
});

it('rechaza mensajes vacios', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', [
            'mensaje' => '   ',
        ]);

    $response->assertStatus(422);
});
