<?php

declare(strict_types=1);

namespace App\Services\Ai\DTO;

/**
 * Provider-agnostic AI completion response.
 *
 * @phpstan-type AiMetadata array<string, mixed>
 */
final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public string $provider,
        public ?string $model = null,
        public array $metadata = [],
    ) {}
}
