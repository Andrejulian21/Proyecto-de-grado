<?php

declare(strict_types=1);

use App\Enums\DocumentConversionError;
use App\Exceptions\DocumentConversionException;
use App\Services\Documents\DocxToMarkdownConverter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\ListItem;

$tempFiles = [];

beforeEach(function () use (&$tempFiles) {
    $this->converter = new DocxToMarkdownConverter;
    $tempFiles = [];
});

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $tempFiles = [];
});

/**
 * @param  list<string>  $tempFiles
 */
function makeTempDocx(PhpWord $phpWord, array &$tempFiles): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'docx_md_'.uniqid('', true).'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    $tempFiles[] = $path;

    return $path;
}

/**
 * PHPWord's writer often stores TYPE_NUMBER as bullet in numbering.xml.
 * Patch numFmt to decimal so ordered-list conversion can be tested realistically.
 *
 * @param  list<string>  $tempFiles
 */
function makeNumberedListDocx(array &$tempFiles): string
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addListItem('Paso uno', 0, null, ListItem::TYPE_NUMBER);
    $section->addListItem('Paso dos', 0, null, ListItem::TYPE_NUMBER);
    $path = makeTempDocx($phpWord, $tempFiles);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $numbering = $zip->getFromName('word/numbering.xml');
    expect($numbering)->not->toBeFalse();
    $patched = str_replace('w:val="bullet"', 'w:val="decimal"', (string) $numbering);
    $zip->deleteName('word/numbering.xml');
    $zip->addFromString('word/numbering.xml', $patched);
    $zip->close();

    return $path;
}

it('converts a simple DOCX document to markdown', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Hola mundo desde DOCX');

    $markdown = $this->converter->convert(makeTempDocx($phpWord, $tempFiles));

    expect($markdown)->toContain('Hola mundo desde DOCX');
});

it('preserves heading hierarchy', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $phpWord->addTitleStyle(1, ['size' => 16, 'bold' => true]);
    $phpWord->addTitleStyle(2, ['size' => 14, 'bold' => true]);
    $section = $phpWord->addSection();
    $section->addTitle('Titulo Principal', 1);
    $section->addTitle('Subtitulo', 2);
    $section->addText('Parrafo de apoyo');

    $markdown = $this->converter->convert(makeTempDocx($phpWord, $tempFiles));

    expect($markdown)
        ->toContain('# Titulo Principal')
        ->toContain('## Subtitulo')
        ->toContain('Parrafo de apoyo');
});

it('preserves bullet lists', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addListItem('Primer item', 0, null, ListItem::TYPE_BULLET_FILLED);
    $section->addListItem('Segundo item', 0, null, ListItem::TYPE_BULLET_FILLED);

    $markdown = $this->converter->convert(makeTempDocx($phpWord, $tempFiles));

    expect($markdown)
        ->toContain('- Primer item')
        ->toContain('- Segundo item');
});

it('preserves numbered lists', function () use (&$tempFiles) {
    $markdown = $this->converter->convert(makeNumberedListDocx($tempFiles));

    expect($markdown)
        ->toContain('1. Paso uno')
        ->toContain('2. Paso dos');
});

it('preserves basic tables when supported', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $table = $section->addTable(['alignment' => JcTable::START]);
    $table->addRow();
    $table->addCell(2000)->addText('Columna A');
    $table->addCell(2000)->addText('Columna B');
    $table->addRow();
    $table->addCell(2000)->addText('Valor 1');
    $table->addCell(2000)->addText('Valor 2');

    $markdown = $this->converter->convert(makeTempDocx($phpWord, $tempFiles));

    expect($markdown)
        ->toContain('| Columna A | Columna B |')
        ->toContain('| Valor 1 | Valor 2 |')
        ->toContain('| --- | --- |');
});

it('rejects invalid extensions', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'not_docx_'.uniqid('', true).'.pdf';
    file_put_contents($path, '%PDF-1.4');
    $tempFiles[] = $path;

    try {
        $this->converter->convert($path);
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::InvalidExtension);
    }
});

it('rejects empty documents', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'empty_'.uniqid('', true).'.docx';
    file_put_contents($path, '');
    $tempFiles[] = $path;

    try {
        $this->converter->convert($path);
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::EmptyDocument);
    }
});

it('rejects corrupt DOCX files', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'corrupt_'.uniqid('', true).'.docx';
    file_put_contents($path, 'esto-no-es-un-docx-valido');
    $tempFiles[] = $path;

    try {
        $this->converter->convert($path);
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::CorruptFile);
    }
});

it('rejects missing files as empty documents', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing_'.uniqid('', true).'.docx';

    try {
        $this->converter->convert($path);
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::EmptyDocument);
    }
});

it('rejects DOCX packages with no extractable text', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $phpWord->addSection();

    try {
        $this->converter->convert(makeTempDocx($phpWord, $tempFiles));
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::EmptyDocument);
    }
});
