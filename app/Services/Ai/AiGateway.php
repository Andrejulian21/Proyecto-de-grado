<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use Throwable;

/**
 * Public entry point for future AI modules.
 * Delegates to AiProvider implementations; never depends on vendor SDKs.
 */
final class AiGateway
{
    public function __construct(
        private readonly AiProviderRegistry $registry,
    ) {}

    /**
     * @throws AiException
     */
    public function complete(AiRequest $request, ?string $provider = null): AiResponse
    {
        try {
            return $this->registry->resolve($provider)->complete($request);
        } catch (AiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw AiException::unexpected($exception);
        }
    }
}
