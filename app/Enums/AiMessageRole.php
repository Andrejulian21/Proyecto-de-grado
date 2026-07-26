<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Provider-agnostic chat/completion message roles.
 */
enum AiMessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
}
