<?php

declare(strict_types=1);

use App\Enums\AiMessageRole;
use App\Services\Ai\DTO\AiMessage;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;

function geminiRequest(string $text = 'Hola'): AiRequest
{
    return new AiRequest(
        messages: [
            AiMessage::system('System prompt primero.'),
            AiMessage::user($text),
        ],
        options: ['feature' => 'chat'],
    );
}

function geminiSuccessPayload(string $text = 'Respuesta'): array
{
    return [
        'candidates' => [
            ['content' => ['parts' => [['text' => $text]]]],
        ],
    ];
}

it('calls versioned generateContent URL with configured model', function () {
    config()->set('ai.gemini.model', 'gemini-2.0-flash');
    config()->set('services.gemini.key', 'KEY_CHAT');
    config()->set('services.gemini.key_2', 'KEY_ANALYSIS');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiSuccessPayload(), 200),
    ]);

    $provider = app(GeminiProvider::class);
    $response = $provider->complete(geminiRequest());

    expect($response->content)->toBe('Respuesta')
        ->and($response->provider)->toBe('gemini')
        ->and($response->model)->toBe('gemini-2.0-flash');

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'v1beta/models/gemini-2.0-flash:generateContent')
            && str_contains((string) $request->url(), 'key=KEY_CHAT');
    });
});

it('retries once on the other key after 429 and propagates Retry-After when both fail', function () {
    config()->set('ai.gemini.model', 'gemini-2.0-flash');
    config()->set('services.gemini.key', 'KEY_ONE');
    config()->set('services.gemini.key_2', 'KEY_TWO');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(null, 429, ['Retry-After' => '20'])
            ->push(geminiSuccessPayload('Recuperado'), 200),
    ]);

    $provider = app(GeminiProvider::class);
    $response = $provider->complete(geminiRequest());

    expect($response->content)->toBe('Recuperado');

    Http::assertSentCount(2);
});

it('throws QuotaExceeded with Retry-After when both keys return 429', function () {
    config()->set('ai.gemini.model', 'gemini-2.0-flash');
    config()->set('services.gemini.key', 'KEY_ONE');
    config()->set('services.gemini.key_2', 'KEY_TWO');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(null, 429, ['Retry-After' => '45']),
    ]);

    $provider = app(GeminiProvider::class);

    try {
        $provider->complete(geminiRequest());
        expect(false)->toBeTrue('Expected AiException was not thrown.');
    } catch (App\Exceptions\AiException $exception) {
        expect($exception->error)->toBe(App\Enums\AiErrorCode::QuotaExceeded);
    }
});

it('throws ProviderTimeout on connection timeout', function () {
    config()->set('ai.gemini.model', 'gemini-2.0-flash');
    config()->set('services.gemini.key', 'KEY_ONE');
    config()->set('services.gemini.key_2', 'KEY_TWO');

    Http::fake([
        'generativelanguage.googleapis.com/*' => function () {
            throw new Illuminate\Http\Client\ConnectionException('Timed out');
        },
    ]);

    $provider = app(GeminiProvider::class);

    try {
        $provider->complete(geminiRequest());
        expect(false)->toBeTrue('Expected AiException was not thrown.');
    } catch (App\Exceptions\AiException $exception) {
        expect($exception->error)->toBe(App\Enums\AiErrorCode::ProviderTimeout);
    }
});

it('logs only allow-listed fields without PII or keys', function () {
    config()->set('ai.gemini.model', 'gemini-2.0-flash');
    config()->set('services.gemini.key', 'FAKE-KEY-ONE-12345');
    config()->set('services.gemini.key_2', 'FAKE-KEY-TWO-67890');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiSuccessPayload('OK'), 200),
    ]);

    $logs = [];
    Illuminate\Support\Facades\Log::shouldReceive('info')
        ->zeroOrMoreTimes()
        ->withArgs(function (string $message, array $context = []) use (&$logs) {
            $logs[] = $message.' '.json_encode($context);

            return true;
        });

    app(GeminiProvider::class)->complete(geminiRequest('Contenido sensible del estudiante'));

    $combined = implode("\n", $logs);

    expect($combined)->not->toContain('FAKE-KEY-ONE-12345')
        ->and($combined)->not->toContain('FAKE-KEY-TWO-67890')
        ->and($combined)->not->toContain('Contenido sensible del estudiante');
});
