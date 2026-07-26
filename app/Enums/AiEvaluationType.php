<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of document AI evaluations. Extensible for ABET / Director later.
 */
enum AiEvaluationType: string
{
    case PreSubmission = 'pre_submission';
    case Abet = 'abet';
    case Director = 'director';
}
