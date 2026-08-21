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

    public static function unexpected(Throwable $previous): self
    {
        return new self(
            AiErrorCode::Unexpected,
            'Ocurrió un error inesperado al procesar la solicitud de IA.',
            $previous,
        );
    }
}
