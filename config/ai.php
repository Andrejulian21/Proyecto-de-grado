<?php

declare(strict_types=1);

use App\Services\Ai\Providers\NullAiProvider;

/**
 * Shared AI infrastructure configuration.
 *
 * Add a new provider by implementing App\Contracts\Ai\AiProvider and
 * registering it under "providers". No gateway changes required.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    |
    | "null" is the safe default until a real provider (e.g. FastAPI bridge)
    | is registered. NullAiProvider refuses to complete requests.
    |
    */
    'default_provider' => env('AI_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Provider map
    |--------------------------------------------------------------------------
    |
    | Keys are stable names used by AiGateway / AI_PROVIDER.
    | Values are concrete AiProvider class names resolved from the container.
    |
    */
    'providers' => [
        'null' => NullAiProvider::class,
        // Future examples (not implemented in this change):
        // 'fastapi' => App\Services\Ai\Providers\FastApiAiProvider::class,
        // 'openai' => App\Services\Ai\Providers\OpenAiProvider::class,
        // 'gemini' => App\Services\Ai\Providers\GeminiProvider::class,
    ],

];
