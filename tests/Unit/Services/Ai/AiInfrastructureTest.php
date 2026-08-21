<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Exceptions\AiException;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPromptComposer;
use App\Services\Ai\AiProviderRegistry;
use App\Services\Ai\DTO\AiMessage;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Providers\NullAiProvider;
use Illuminate\Container\Container;

final class StubAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'stub';
    }

    public function complete(AiRequest $request): AiResponse
    {
        $last = $request->messages[array_key_last($request->messages)]->content ?? '';

        return new AiResponse(
            content: 'echo:'.$last,
            provider: $this->name(),
            model: $request->model,
            metadata: ['messages' => count($request->messages)],
        );
    }
}

it('resolves providers from the registry and completes via the gateway', function () {
    $stub = new StubAiProvider;
    $registry = new AiProviderRegistry(
        new Container,
        ['stub' => $stub, 'null' => new NullAiProvider],
        'stub',
    );
    $gateway = new AiGateway($registry);

    $response = $gateway->complete(new AiRequest([
        AiMessage::system('Instrucciones genéricas'),
        AiMessage::user('Hola'),
    ]));

    expect($response->content)->toBe('echo:Hola')
        ->and($response->provider)->toBe('stub')
        ->and($response->metadata['messages'])->toBe(2)
        ->and($registry->names())->toContain('stub', 'null');
});

it('allows selecting an explicit provider name', function () {
    $stub = new StubAiProvider;
    $registry = new AiProviderRegistry(
        new Container,
        ['stub' => $stub, 'null' => new NullAiProvider],
        'null',
    );
    $gateway = new AiGateway($registry);

    $response = $gateway->complete(
        new AiRequest([AiMessage::user('Seleccionado')]),
        'stub',
    );

    expect($response->provider)->toBe('stub')
        ->and($response->content)->toBe('echo:Seleccionado');
});

it('throws for an unknown provider', function () {
    $registry = new AiProviderRegistry(
        new Container,
        ['null' => new NullAiProvider],
        'null',
    );
    $gateway = new AiGateway($registry);

    try {
        $gateway->complete(new AiRequest([AiMessage::user('x')]), 'openai');
        $this->fail('Expected AiException');
    } catch (AiException $exception) {
        expect($exception->error)->toBe(AiErrorCode::UnknownProvider);
    }
});

it('null provider refuses to complete without external calls', function () {
    $provider = new NullAiProvider;
    $registry = new AiProviderRegistry(
        new Container,
        ['null' => $provider],
        'null',
    );
    $gateway = new AiGateway($registry);

    expect($provider->name())->toBe('null');

    try {
        $gateway->complete(new AiRequest([AiMessage::user('no debe llamar APIs')]));
        $this->fail('Expected AiException');
    } catch (AiException $exception) {
        expect($exception->error)->toBe(AiErrorCode::ProviderNotConfigured);
    }
});

it('rejects empty message lists on AiRequest', function () {
    try {
        new AiRequest([]);
        $this->fail('Expected AiException');
    } catch (AiException $exception) {
        expect($exception->error)->toBe(AiErrorCode::InvalidRequest);
    }
});

it('composes generic labeled prompt sections', function () {
    $composer = new AiPromptComposer;

    $text = $composer->compose([
        ['title' => 'Contexto', 'body' => 'Texto A'],
        ['body' => 'Texto B sin título'],
        ['title' => 'Vacío', 'body' => '   '],
    ]);

    expect($text)
        ->toContain('## Contexto')
        ->toContain('Texto A')
        ->toContain('Texto B sin título')
        ->not->toContain('## Vacío');
});

it('exposes factory helpers for message roles', function () {
    expect(AiMessage::system('s')->role)->toBe(AiMessageRole::System)
        ->and(AiMessage::user('u')->role)->toBe(AiMessageRole::User)
        ->and(AiMessage::assistant('a')->role)->toBe(AiMessageRole::Assistant);
});

it('resolves provider classes from the container via registry', function () {
    $container = new Container;
    $registry = new AiProviderRegistry(
        $container,
        ['null' => NullAiProvider::class],
        'null',
    );

    $resolved = $registry->resolve('null');

    expect($resolved)->toBeInstanceOf(NullAiProvider::class)
        ->and($registry->defaultName())->toBe('null');
});

it('binds AiGateway in the application container', function () {
    $gateway = app(AiGateway::class);

    expect($gateway)->toBeInstanceOf(AiGateway::class);

    try {
        $gateway->complete(new AiRequest([AiMessage::user('wiring')]));
        $this->fail('Expected AiException from null default provider');
    } catch (AiException $exception) {
        expect($exception->error)->toBe(AiErrorCode::ProviderNotConfigured);
    }
});
