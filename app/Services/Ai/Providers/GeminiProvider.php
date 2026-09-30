<?php

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\AiProvider;
use App\Enums\AiMessageRole;
use App\Exceptions\AiException;
use App\Services\Ai\DTO\AiMessage;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gemini REST provider via generateContent.
 * Key-per-feature (chat → key 1, analysis → key 2) with one immediate
 * cross-key retry on 429/5xx; upstream Retry-After is propagated, never slept.
 */
final class GeminiProvider implements AiProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function complete(AiRequest $request): AiResponse
    {
        $model = (string) config('ai.gemini.model', 'gemini-3.8-flash');
        $feature = (string) ($request->options['feature'] ?? 'chat');
        $timeout = $feature === 'analysis'
            ? (int) config('ai.gemini.analysis_timeout', 60)
            : (int) config('ai.gemini.chat_timeout', 30);

        $keys = $feature === 'analysis'
            ? [(string) config('services.gemini.key_2'), (string) config('services.gemini.key')]
            : [(string) config('services.gemini.key'), (string) config('services.gemini.key_2')];

        $keys = array_values(array_filter($keys, static fn (string $key): bool => $key !== ''));

        if ($keys === []) {
            throw AiException::providerNotConfigured($this->name());
        }

        $prompt = $this->promptText($request);
        $promptChars = mb_strlen($prompt);
        $promptHash = hash('sha256', $prompt);
        $started = hrtime(true);
        $lastRetryAfter = 60;

        foreach ($keys as $index => $key) {
            try {
                $response = Http::timeout($timeout)
                    ->post($this->endpoint($model, $key), [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                    ]);
            } catch (ConnectionException $exception) {
                $this->logCall($promptChars, $promptHash, 0, $this->latencyMs($started), $index);

                throw AiException::providerTimeout();
            } catch (Throwable $exception) {
                $this->logCall($promptChars, $promptHash, 0, $this->latencyMs($started), $index);

                throw AiException::providerFailed('No fue posible completar la solicitud con el servicio de IA.', $exception);
            }

            $status = $response->status();
            $lastRetryAfter = $this->retryAfterSeconds($response);

            if ($status === 429 || $status >= 500) {
                $this->logCall($promptChars, $promptHash, $status, $this->latencyMs($started), $index);

                if ($index < count($keys) - 1) {
                    continue;
                }

                throw AiException::quotaExceeded($lastRetryAfter);
            }

            if (! $response->successful()) {
                $this->logCall($promptChars, $promptHash, $status, $this->latencyMs($started), $index);

                throw AiException::providerFailed("El proveedor de IA respondió con estado {$status}.");
            }

            $content = $this->extractText($response);
            $this->logCall($promptChars, $promptHash, $status, $this->latencyMs($started), $index, mb_strlen($content));

            return new AiResponse(
                content: $content,
                provider: $this->name(),
                model: $model,
                metadata: ['feature' => $feature],
            );
        }

        throw AiException::quotaExceeded($lastRetryAfter);
    }

    private function endpoint(string $model, string $key): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
    }

    private function promptText(AiRequest $request): string
    {
        $parts = [];

        foreach ($request->messages as $message) {
            if (! $message instanceof AiMessage) {
                continue;
            }

            $label = $message->role === AiMessageRole::System ? 'SYSTEM' : strtoupper($message->role->value);
            $parts[] = "[{$label}]\n".$message->content;
        }

        return implode("\n\n", $parts);
    }

    private function extractText(Response $response): string
    {
        $texts = [];

        foreach ((array) ($response->json('candidates', [])) as $candidate) {
            foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $texts[] = $part['text'];
                }
            }
        }

        return implode('', $texts);
    }

    private function retryAfterSeconds(Response $response): int
    {
        $header = $response->header('Retry-After');

        if (is_numeric($header) && (int) $header > 0) {
            return (int) $header;
        }

        return 60;
    }

    private function latencyMs(int $startedHrtime): int
    {
        return (int) ((hrtime(true) - $startedHrtime) / 1_000_000);
    }

    private function logCall(
        int $promptChars,
        string $promptHash,
        int $status,
        int $latencyMs,
        int $keyIndex,
        int $responseChars = 0,
    ): void {
        Log::info('gemini.generateContent', [
            'prompt_chars' => $promptChars,
            'response_chars' => $responseChars,
            'sha256_prefix' => substr($promptHash, 0, 12),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'key_index' => $keyIndex,
        ]);
    }
}
