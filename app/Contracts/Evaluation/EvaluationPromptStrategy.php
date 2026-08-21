<?php

declare(strict_types=1);

namespace App\Contracts\Evaluation;

use App\Enums\AiEvaluationType;
use App\Services\Evaluation\DTO\EvaluationContext;

/**
 * Builds prompts for a specific evaluation kind (pre-submission, ABET, …).
 * Strategies never talk to AI providers directly.
 */
interface EvaluationPromptStrategy
{
    public function type(): AiEvaluationType;

    public function promptVersion(): string;

    public function systemInstructions(): string;

    /**
     * @return list<array{title?: string, body: string}>
     */
    public function contextSections(EvaluationContext $context): array;
}
