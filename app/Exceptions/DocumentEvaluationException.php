<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain errors for document evaluation orchestration (not AI-provider failures).
 */
final class DocumentEvaluationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'No se encontró la entrega solicitada.'): self
    {
        return new self('not_found', $message, 404);
    }

    public static function forbidden(string $message = 'No tienes permiso para analizar esta entrega.'): self
    {
        return new self('forbidden', $message, 403);
    }

    public static function invalidDocument(string $message): self
    {
        return new self('invalid_document', $message, 422);
    }

    public static function notAnalyzable(
        string $message = 'Este documento no está configurado para análisis mediante IA.',
    ): self {
        return new self('document_not_analyzable', $message, 422);
    }
}
