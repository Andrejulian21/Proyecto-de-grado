<?php

declare(strict_types=1);

use App\Services\Ai\Providers\GeminiProvider;
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
        'gemini' => GeminiProvider::class,
        // Future examples (not implemented in this change):
        // 'fastapi' => App\Services\Ai\Providers\FastApiAiProvider::class,
        // 'openai' => App\Services\Ai\Providers\OpenAiProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini provider settings
    |--------------------------------------------------------------------------
    |
    | Model defaults to gemini-2.0-flash (stable free-tier); override with
    | AI_GEMINI_MODEL. Timeouts are per feature in seconds.
    |
    */
    'gemini' => [
        'model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        'chat_timeout' => 30,
        'analysis_timeout' => 60,
    ],

];
