<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentFormat;
use ZipArchive;

/**
 * Identifies DOCX vs PDF from file bytes, not from the user-supplied name alone.
 */
final class DocumentFormatDetector
{
    public function detect(string $absolutePath, ?string $originalName = null, ?string $mime = null): DocumentFormat
    {
        unset($originalName, $mime);

        if (! is_file($absolutePath) || ! is_readable($absolutePath) || filesize($absolutePath) === 0) {
            return DocumentFormat::Unsupported;
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return DocumentFormat::Unsupported;
        }

        $magic = fread($handle, 5);
        fclose($handle);

        if (! is_string($magic) || $magic === '') {
            return DocumentFormat::Unsupported;
        }

        if (str_starts_with($magic, '%PDF')) {
            return DocumentFormat::Pdf;
        }

        if (str_starts_with($magic, 'PK') && $this->zipContainsWordDocument($absolutePath)) {
            return DocumentFormat::Docx;
        }

        return DocumentFormat::Unsupported;
    }

    private function zipContainsWordDocument(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }

        $found = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $found;
    }
}
