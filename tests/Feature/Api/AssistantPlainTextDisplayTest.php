<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\AiMessageRole;
use App\Enums\UserRole;
use App\Models\AiAssistantMessage;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderRegistry;
use App\Services\Ai\DTO\AiMessage;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\NullAiProvider;
use App\Services\Assistant\Strategies\StudentOrientationStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Guard: any real HTTP call fails the test (stray request exception).
    // Http::fake() is applied per-test where an HTTP stub is needed.
    Http::preventStrayRequests();

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
});

function bindPlainTextStubProvider(string $json): void
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

function boldOrientationPayload(): string
{
    return json_encode([
        'mensaje' => 'Hola, te recomiendo **Ingeniería de Software** como línea principal.',
        'resumen_conversacion' => 'Resumen.',
        'idea_refinada' => 'Idea refinada.',
        'lineas_investigacion' => ['IA'],
        'tecnologias_recomendadas' => ['Python'],
        'metodologias_sugeridas' => ['SCRUM'],
        'directores_recomendados' => [],
        'riesgos' => ['Ninguno'],
        'proximos_pasos' => ['Definir alcance'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

it('stores the assistant display message without bold markers but keeps structured_json intact', function () {
    bindPlainTextStubProvider(boldOrientationPayload());

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/estudiante/asistente/mensajes', ['mensaje' => 'Hola']);

    $response->assertOk();

    $displayed = (string) $response->json('data.mensaje_asistente.content');

    expect($displayed)->not->toContain('**')
        ->and($displayed)->toContain('Ingeniería de Software');

    $stored = AiAssistantMessage::query()
        ->where('role', AiMessageRole::Assistant->value)
        ->latest('id')
        ->firstOrFail();

    expect((string) $stored->content)->not->toContain('**')
        ->and((string) $stored->content)->toContain('Ingeniería de Software')
        ->and($stored->structured_json['mensaje'])->toContain('**Ingeniería de Software**');
});

it('instructs plain-text Spanish without markdown in the system prompt', function () {
    $instructions = app(StudentOrientationStrategy::class)->systemInstructions();

    expect($instructions)->toContain('texto plano')
        ->and($instructions)->toContain('markdown')
        ->and($instructions)->toContain('sin **');
});

it('preserves raw provider content with bold markers before parsing', function () {
    config(['services.gemini.key' => 'test-key-1']);
    config(['services.gemini.key_2' => 'test-key-2']);

    Http::fake([
        '*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => boldOrientationPayload()]]]],
            ],
        ], 200),
    ]);

    $response = app(GeminiProvider::class)->complete(
        new AiRequest([AiMessage::user('hola')], options: ['feature' => 'chat'])
    );

    expect($response->content)->toContain('**Ingeniería de Software**');
});
