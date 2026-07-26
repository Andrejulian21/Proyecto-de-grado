<?php

declare(strict_types=1);

namespace App\Contracts\Evaluation;

use App\Exceptions\AiException;

/**
 * Parses provider-agnostic AI text into a structured evaluation array for persistence/UI.
 */
interface EvaluationResultInterpreter
{
    /**
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function interpret(string $rawContent): array;
}
