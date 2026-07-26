<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPromptComposer;
use App\Services\Ai\AiProviderRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Binds shared AI infrastructure (no use-case / no vendor SDKs).
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderRegistry::class, function ($app): AiProviderRegistry {
            /** @var array<string, class-string> $providers */
            $providers = (array) config('ai.providers', []);
            $default = (string) config('ai.default_provider', 'null');

            return new AiProviderRegistry($app, $providers, $default);
        });

        $this->app->singleton(AiGateway::class, function ($app): AiGateway {
            return new AiGateway($app->make(AiProviderRegistry::class));
        });

        $this->app->singleton(AiPromptComposer::class);
    }
}
