<?php

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Contracts\Ai\AiProvider;
use App\Exceptions\AiException;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;

/**
 * Support-only provider: wires the infrastructure without external AI calls.
 * Invoking complete() fails loudly so production never silently "succeeds".
 */
final class NullAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function complete(AiRequest $request): AiResponse
    {
        throw AiException::providerNotConfigured($this->name());
    }
}
