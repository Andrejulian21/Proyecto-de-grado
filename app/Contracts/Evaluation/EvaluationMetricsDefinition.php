<?php

declare(strict_types=1);

namespace App\Contracts\Evaluation;

/**
 * Replaceable metric set for an evaluation type.
 * New rubrics = new implementation; AI core stays unchanged.
 */
interface EvaluationMetricsDefinition
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<array{id: string, nombre: string, descripcion: string}>
     */
    public function criteria(): array;

    /**
     * Human-readable block injected into prompts.
     */
    public function promptSectionBody(): string;
}
