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
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function invalidMessage(string $message = 'El mensaje no es válido.'): self
    {
        return new self($message, 422, 'invalid_message');
    }

    public static function notEligible(): self
    {
        return new self(
            'Este chat de orientación es de un solo uso y está disponible solo para estudiantes sin director ni proyecto asignado.',
            403,
            'chat_not_eligible',
        );
    }

    public static function singleUseExhausted(): self
    {
        return new self(
            'Alcanzaste el límite de 20 mensajes de orientación. Este chat es de un solo uso para estudiantes sin director ni proyecto asignado.',
            403,
            'chat_single_use_exhausted',
        );
    }

    public static function throttleExceeded(int $retryAfterSeconds = 60): self
    {
        return new self(
            "Límite de cuota de IA alcanzado. Inténtalo de nuevo en {$retryAfterSeconds} segundos.",
            429,
            'ai_quota_exceeded',
            $retryAfterSeconds,
        );
    }
}
