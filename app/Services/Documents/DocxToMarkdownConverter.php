<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DocumentConversionException;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PhpOffice\PhpWord\Style\Numbering;
use Throwable;

/**
 * Reusable local DOCX → Markdown converter.
 *
 * Decoupled from domain modules (entregas, proyectos, roles) and AI providers.
 * Walks the PHPWord element tree so structure survives better than the HTML writer.
 */
final class DocxToMarkdownConverter
{
    /**
     * Convert a local DOCX file to Markdown.
     *
     * @throws DocumentConversionException
     */
    public function convert(string $absolutePath): string
    {
        try {
            $this->assertValidExtension($absolutePath);
            $this->assertReadableNonEmptyFile($absolutePath);

            $phpWord = $this->loadDocument($absolutePath);
            $markdown = $this->documentToMarkdown($phpWord);

            if ($this->isBlank($markdown)) {
                throw DocumentConversionException::emptyDocument();
            }

            return $markdown;
        } catch (DocumentConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw DocumentConversionException::unexpected($exception);
        }
    }

    private function assertValidExtension(string $path): void
    {
        if (! str_ends_with(strtolower($path), '.docx')) {
            throw DocumentConversionException::invalidExtension($path);
        }
    }

    private function assertReadableNonEmptyFile(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw DocumentConversionException::emptyDocument();
        }

        if (filesize($path) === 0) {
            throw DocumentConversionException::emptyDocument();
        }

        if (! $this->looksLikeZip($path)) {
            throw DocumentConversionException::corruptFile();
        }
    }

    private function looksLikeZip(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        return is_string($magic) && str_starts_with($magic, 'PK');
    }

    private function loadDocument(string $path): PhpWord
    {
        try {
            return IOFactory::load($path);
        } catch (Throwable $exception) {
            throw DocumentConversionException::corruptFile($exception);
        }
    }

    private function documentToMarkdown(PhpWord $phpWord): string
    {
        try {
            $blocks = [];
            $orderedCounters = [];

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $block = $this->elementToMarkdown($element, $orderedCounters);
                    if ($block !== null && ! $this->isBlank($block)) {
                        $blocks[] = rtrim($block);
                    }
                }
            }

            return trim(implode("\n\n", $blocks));
        } catch (DocumentConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw DocumentConversionException::conversionFailed($exception);
        }
    }

    /**
     * @param  array<string, int>  $orderedCounters
     */
    private function elementToMarkdown(AbstractElement $element, array &$orderedCounters): ?string
    {
        return match (true) {
            $element instanceof Title => $this->titleToMarkdown($element),
            $element instanceof ListItem, $element instanceof ListItemRun => $this->listItemToMarkdown(
                $element,
                $orderedCounters,
            ),
            $element instanceof Table => $this->tableToMarkdown($element),
            $element instanceof TextBreak => '',
            $element instanceof Text, $element instanceof TextRun => $this->plainTextBlock($element),
            default => $this->fallbackElementText($element),
        };
    }

    private function titleToMarkdown(Title $title): string
    {
        $level = max(1, min(6, (int) $title->getDepth()));

        return str_repeat('#', $level).' '.$this->extractText($title);
    }

    /**
     * @param  array<string, int>  $orderedCounters
     */
    private function listItemToMarkdown(ListItem|ListItemRun $item, array &$orderedCounters): string
    {
        $text = $this->extractText($item);
        $depth = max(0, (int) $item->getDepth());
        $indent = str_repeat('  ', $depth);

        $style = $item->getStyle();
        $numStyleName = is_object($style) && method_exists($style, 'getNumStyle')
            ? (string) $style->getNumStyle()
            : '';
        $listType = is_object($style) && method_exists($style, 'getListType')
            ? $style->getListType()
            : null;

        $format = $this->resolveListFormat($numStyleName, $depth);
        $isOrdered = $this->isOrderedList($format, $listType);
        $counterKey = ($numStyleName !== '' ? $numStyleName : 'default').'@'.$depth;

        if ($isOrdered) {
            $orderedCounters[$counterKey] = ($orderedCounters[$counterKey] ?? 0) + 1;

            return $indent.$orderedCounters[$counterKey].'. '.$text;
        }

        return $indent.'- '.$text;
    }

    private function resolveListFormat(string $numStyleName, int $depth): ?string
    {
        if ($numStyleName === '') {
            return null;
        }

        $numbering = Style::getStyle($numStyleName);
        if (! $numbering instanceof Numbering) {
            return null;
        }

        $levels = $numbering->getLevels();
        $level = $levels[$depth] ?? $levels[0] ?? null;

        if ($level === null) {
            return null;
        }

        $format = $level->getFormat();

        return is_string($format) && $format !== '' ? $format : null;
    }

    private function isOrderedList(?string $format, mixed $listType): bool
    {
        if (is_string($format)) {
            return ! in_array(strtolower($format), ['bullet', 'none'], true);
        }

        if (is_int($listType)) {
            return in_array($listType, [
                ListItemStyle::TYPE_NUMBER,
                ListItemStyle::TYPE_NUMBER_NESTED,
                ListItemStyle::TYPE_ALPHANUM,
            ], true);
        }

        return false;
    }

    private function tableToMarkdown(Table $table): string
    {
        $rows = [];

        foreach ($table->getRows() as $row) {
            $cells = [];
            foreach ($row->getCells() as $cell) {
                $cells[] = $this->escapeTableCell($this->extractText($cell));
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === []) {
            return '';
        }

        $columnCount = max(array_map('count', $rows));
        $normalized = array_map(
            static fn (array $cells): array => array_pad($cells, $columnCount, ''),
            $rows,
        );

        $header = $normalized[0];
        $lines = [];
        $lines[] = '| '.implode(' | ', $header).' |';
        $lines[] = '| '.implode(' | ', array_fill(0, $columnCount, '---')).' |';

        foreach (array_slice($normalized, 1) as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return implode("\n", $lines);
    }

    private function escapeTableCell(string $text): string
    {
        $text = str_replace('|', '\\|', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function plainTextBlock(AbstractElement $element): string
    {
        return $this->extractText($element);
    }

    private function fallbackElementText(AbstractElement $element): ?string
    {
        if (method_exists($element, 'getElements')) {
            $parts = [];
            foreach ($element->getElements() as $child) {
                if ($child instanceof AbstractElement) {
                    $text = $this->extractText($child);
                    if (! $this->isBlank($text)) {
                        $parts[] = $text;
                    }
                }
            }

            return $parts === [] ? null : implode(' ', $parts);
        }

        if (method_exists($element, 'getText')) {
            $text = $this->extractText($element);

            return $this->isBlank($text) ? null : $text;
        }

        return null;
    }

    private function extractText(mixed $node): string
    {
        if ($node instanceof Text) {
            return (string) $node->getText();
        }

        if (is_string($node)) {
            return $node;
        }

        if (! is_object($node)) {
            return '';
        }

        if (method_exists($node, 'getText')) {
            $text = $node->getText();
            if (is_string($text)) {
                return $text;
            }
            if (is_object($text)) {
                return $this->extractText($text);
            }
        }

        if (method_exists($node, 'getElements')) {
            $parts = [];
            foreach ($node->getElements() as $child) {
                $part = $this->extractText($child);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return implode('', $parts);
        }

        return '';
    }

    private function isBlank(string $value): bool
    {
        return trim(preg_replace('/\s+/u', '', $value) ?? '') === '';
    }
}
