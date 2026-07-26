<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Typed error codes for document conversion failures.
 * Future consumers can branch on these without knowing the converter internals.
 */
enum DocumentConversionError: string
{
    case InvalidExtension = 'invalid_extension';
    case EmptyDocument = 'empty_document';
    case CorruptFile = 'corrupt_file';
    case UnprocessableContent = 'unprocessable_content';
    case ConversionFailed = 'conversion_failed';
    case Unexpected = 'unexpected';
}
