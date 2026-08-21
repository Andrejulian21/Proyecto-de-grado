<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Interpreters;

use App\Contracts\Evaluation\EvaluationResultInterpreter;
use App\Services\Evaluation\EvaluationResultParser;

/**
 * Adapts the existing pre-submission parser to the pluggable interpreter contract.
 */
final class PreSubmissionResultInterpreter implements EvaluationResultInterpreter
{
    public function __construct(
        private readonly EvaluationResultParser $parser,
    ) {}

    public function interpret(string $rawContent): array
    {
        return $this->parser->parse($rawContent)->toArray();
    }
}
