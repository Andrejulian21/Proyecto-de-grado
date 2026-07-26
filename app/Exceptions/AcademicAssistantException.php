<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain errors for the academic assistant (authz / validation), not AI infra.
 */
final class AcademicAssistantException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly string $errorCode = 'assistant_error',
    ) {
        parent::__construct($message);
    }

    public static function invalidMessage(string $message = 'El mensaje no es válido.'): self
    {
        return new self($message, 422, 'invalid_message');
    }
}
