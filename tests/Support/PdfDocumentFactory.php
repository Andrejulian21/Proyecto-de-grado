<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Builds a minimal one-page PDF with visible Helvetica text for parser tests.
 */
final class PdfDocumentFactory
{
    /**
     * @param  list<string>  $tempFiles
     */
    public static function write(string $text, array &$tempFiles, string $suffix = '.pdf'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'abet_pdf_'.uniqid('', true).$suffix;
        file_put_contents($path, self::bytes($text));
        $tempFiles[] = $path;

        return $path;
    }

    public static function bytes(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = 'BT /F1 12 Tf 72 720 Td ('.$escaped.') Tj ET';

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '4 0 obj << /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $header = "%PDF-1.4\n";
        $body = '';
        $offsets = [0];
        $position = strlen($header);

        foreach ($objects as $object) {
            $offsets[] = $position;
            $chunk = $object."\n";
            $body .= $chunk;
            $position += strlen($chunk);
        }

        $xref = 'xref'."\n".'0 '.(count($objects) + 1)."\n";
        $xref .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $trailer = 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n"
            .'startxref'."\n".$position."\n"
            .'%%EOF'."\n";

        return $header.$body.$xref.$trailer;
    }
}
