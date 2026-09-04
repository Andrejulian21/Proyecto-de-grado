<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DocumentConversionException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Local PDF → Markdown converter. Does not call AI providers.
 */
final class PdfToMarkdownConverter
{
    public function __construct(
        private readonly Parser $parser = new Parser,
    ) {}

    /**
     * @throws DocumentConversionException
     */
    public function convert(string $absolutePath): string
    {
        try {
            $this->assertReadablePdf($absolutePath);

            $document = $this->parser->parseFile($absolutePath);
            $pages = $document->getPages();
            $blocks = [];

            foreach ($pages as $index => $page) {
                $text = trim((string) $page->getText());

                if ($this->isBlank($text)) {
                    continue;
                }

                if (count($pages) > 1) {
                    $blocks[] = '## Página '.($index + 1)."\n\n".$text;
                } else {
                    $blocks[] = $text;
                }
            }

            $markdown = trim(implode("\n\n", $blocks));

            if ($this->isBlank($markdown)) {
                throw DocumentConversionException::emptyDocument();
            }

            return $markdown;
        } catch (DocumentConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw DocumentConversionException::corruptFile($exception, 'PDF');
        }
    }

    private function assertReadablePdf(string $path): void
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) === 0) {
            throw DocumentConversionException::emptyDocument();
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw DocumentConversionException::emptyDocument();
        }

        $magic = fread($handle, 5);
        fclose($handle);

        if (! is_string($magic) || ! str_starts_with($magic, '%PDF')) {
            throw DocumentConversionException::corruptFile(null, 'PDF');
        }
    }

    private function isBlank(string $value): bool
    {
        return trim(preg_replace('/\s+/u', '', $value) ?? '') === '';
    }
}
