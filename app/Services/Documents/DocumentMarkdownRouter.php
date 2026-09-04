<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentFormat;
use App\Exceptions\DocumentConversionException;

/**
 * Selects the local DOCX or PDF converter after sniffing the file.
 */
final class DocumentMarkdownRouter
{
    public function __construct(
        private readonly DocumentFormatDetector $detector,
        private readonly DocxToMarkdownConverter $docxConverter,
        private readonly PdfToMarkdownConverter $pdfConverter,
    ) {}

    /**
     * @throws DocumentConversionException
     */
    public function convert(string $absolutePath, ?string $originalName = null, ?string $mime = null): string
    {
        $format = $this->detector->detect($absolutePath, $originalName, $mime);

        return match ($format) {
            DocumentFormat::Docx => $this->convertDocx($absolutePath),
            DocumentFormat::Pdf => $this->pdfConverter->convert($absolutePath),
            DocumentFormat::Unsupported => throw DocumentConversionException::unsupportedFormat(),
        };
    }

    private function convertDocx(string $absolutePath): string
    {
        if (str_ends_with(strtolower($absolutePath), '.docx')) {
            return $this->docxConverter->convert($absolutePath);
        }

        $temp = $absolutePath.'.docx';

        if (! copy($absolutePath, $temp)) {
            throw DocumentConversionException::conversionFailed();
        }

        try {
            return $this->docxConverter->convert($temp);
        } finally {
            @unlink($temp);
        }
    }
}
