<?php

declare(strict_types=1);

namespace App\Services\Ai\DTO;

use App\Exceptions\AiException;

/**
 * Provider-agnostic AI completion request.
 *
 * @phpstan-type AiOptions array<string, mixed>
 */
final readonly class AiRequest
{
    /**
     * @param  list<AiMessage>  $messages
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public array $options = [],
    ) {
        if ($this->messages === []) {
            throw AiException::invalidRequest('La solicitud de IA debe incluir al menos un mensaje.');
        }
    }
}
