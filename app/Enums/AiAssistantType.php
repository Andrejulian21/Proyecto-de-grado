<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Discriminator for specialized AI assistants (student, future director/coordinador…).
 */
enum AiAssistantType: string
{
    case StudentOrientation = 'student_orientation';
}
