<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\DocumentConversionError;
use RuntimeException;
use Throwable;

/**
 * Reusable exception for DOCX → Markdown conversion failures.
 * Carries a typed error code so future modules can handle cases uniformly.
 */
final class DocumentConversionException extends RuntimeException
{
    public function __construct(
        public readonly DocumentConversionError $error,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidExtension(string $path): self
    {
        $name = basename($path);

        return new self(
            DocumentConversionError::InvalidExtension,
            "El archivo \"{$name}\" debe tener extensión .docx.",
        );
    }

    public static function emptyDocument(): self
    {
        return new self(
            DocumentConversionError::EmptyDocument,
            'El documento está vacío o no contiene texto procesable.',
        );
    }

    public static function corruptFile(?Throwable $previous = null): self
    {
        return new self(
            DocumentConversionError::CorruptFile,
            'El archivo DOCX está corrupto o no es válido.',
            $previous,
        );
    }

    public static function unprocessableContent(?Throwable $previous = null): self
    {
        return new self(
            DocumentConversionError::UnprocessableContent,
            'El contenido del documento no pudo procesarse a Markdown.',
            $previous,
        );
    }

    public static function conversionFailed(?Throwable $previous = null): self
    {
        return new self(
            DocumentConversionError::ConversionFailed,
            'La conversión del documento a Markdown falló.',
            $previous,
        );
    }

    public static function unexpected(Throwable $previous): self
    {
        return new self(
            DocumentConversionError::Unexpected,
            'Ocurrió un error inesperado al convertir el documento.',
            $previous,
        );
    }
}
