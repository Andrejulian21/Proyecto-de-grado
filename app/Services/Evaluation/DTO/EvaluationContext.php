<?php

declare(strict_types=1);

namespace App\Services\Evaluation\DTO;

/**
 * Provider-agnostic context for document evaluations.
 */
final readonly class EvaluationContext
{
    public function __construct(
        public string $documentMarkdown,
        public string $entregaTitle,
        public string $phase,
        public string $proyectoTitle,
        public string $proyectoCode,
        public ?string $description,
        public string $originalFileName,
    ) {}
}
