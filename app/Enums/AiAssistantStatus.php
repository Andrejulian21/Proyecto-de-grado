<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an AI assistant conversation (last-turn oriented).
 */
enum AiAssistantStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
