<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Strategies;

use App\Contracts\Evaluation\EvaluationPromptStrategy;
use App\Enums\AiEvaluationType;
use App\Services\Evaluation\DTO\EvaluationContext;
use App\Services\Evaluation\Prompts\PreliminaryAnalysisPrompt;

/**
 * Director-facing preliminary analysis of an official delivery document.
 * Same prompt as the student flow; type() preserves the existing endpoint discriminator.
 */
final class AbetDirectorEvaluationStrategy implements EvaluationPromptStrategy
{
    public function __construct(
        private readonly PreliminaryAnalysisPrompt $prompt,
    ) {}

    public function type(): AiEvaluationType
    {
        return AiEvaluationType::Abet;
    }

    public function promptVersion(): string
    {
        return $this->prompt->promptVersion();
    }

    public function systemInstructions(): string
    {
        return $this->prompt->systemInstructions();
    }

    public function contextSections(EvaluationContext $context): array
    {
        return $this->prompt->contextSections($context);
    }
}
