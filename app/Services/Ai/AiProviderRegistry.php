<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\AiProvider;
use App\Exceptions\AiException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves AiProvider implementations by name (Open/Closed registration point).
 */
final class AiProviderRegistry
{
    /**
     * @param  array<string, class-string<AiProvider>|AiProvider>  $providers
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $providers,
        private readonly string $defaultProvider,
    ) {}

    public function defaultName(): string
    {
        return $this->defaultProvider;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }

    public function resolve(?string $name = null): AiProvider
    {
        $key = $name ?? $this->defaultProvider;

        if (! array_key_exists($key, $this->providers)) {
            throw AiException::unknownProvider($key);
        }

        $entry = $this->providers[$key];

        if ($entry instanceof AiProvider) {
            return $entry;
        }

        /** @var AiProvider $provider */
        $provider = $this->container->make($entry);

        return $provider;
    }
}
