<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AiErrorCode;
use RuntimeException;
use Throwable;

/**
 * Reusable exception for AI infrastructure failures.
 * Future modules can branch on {@see $error} without knowing providers.
 */
final class AiException extends RuntimeException
{
    public function __construct(
        public readonly AiErrorCode $error,
        string $message,
        ?Throwable $previous = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unknownProvider(string $name): self
    {
        return new self(
            AiErrorCode::UnknownProvider,
            "El proveedor de IA \"{$name}\" no está registrado.",
        );
    }

    public static function providerNotConfigured(string $name): self
    {
        return new self(
            AiErrorCode::ProviderNotConfigured,
            "El proveedor de IA \"{$name}\" no está configurado para ejecutar solicitudes.",
        );
    }

    public static function invalidRequest(string $message): self
    {
        return new self(AiErrorCode::InvalidRequest, $message);
    }

    public static function providerFailed(string $message, ?Throwable $previous = null): self
    {
        return new self(AiErrorCode::ProviderFailed, $message, $previous);
    }

    public static function quotaExceeded(int $retryAfterSeconds = 60): self
    {
        return new self(
            AiErrorCode::QuotaExceeded,
            "Límite de cuota de IA alcanzado. Inténtalo de nuevo en {$retryAfterSeconds} segundos.",
            null,
            $retryAfterSeconds,
        );
    }

    public static function providerTimeout(): self
    {
        return new self(
            AiErrorCode::ProviderTimeout,
            'El análisis tardó demasiado. Inténtalo de nuevo.',
        );
    }

    public static function unexpected(Throwable $previous): self
    {
        return new self(
            AiErrorCode::Unexpected,
            'Ocurrió un error inesperado al procesar la solicitud de IA.',
            $previous,
        );
    }
}
