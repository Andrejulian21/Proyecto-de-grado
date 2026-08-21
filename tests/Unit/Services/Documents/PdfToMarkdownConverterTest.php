<?php

declare(strict_types=1);

use App\Enums\DocumentConversionError;
use App\Exceptions\DocumentConversionException;
use App\Services\Documents\PdfToMarkdownConverter;
use Tests\Support\PdfDocumentFactory;

$tempFiles = [];

beforeEach(function () use (&$tempFiles) {
    $this->converter = new PdfToMarkdownConverter;
    $tempFiles = [];
});

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

it('convierte un PDF valido a Markdown con el texto extraido', function () use (&$tempFiles) {
    $path = PdfDocumentFactory::write('Hola mundo desde PDF para ABET', $tempFiles);

    $markdown = $this->converter->convert($path);

    expect($markdown)->toContain('Hola mundo desde PDF para ABET');
});

it('rechaza un PDF corrupto con error controlado', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'corrupt_'.uniqid('', true).'.pdf';
    file_put_contents($path, '%PDF-1.4 esto no es un pdf valido');
    $tempFiles[] = $path;

    try {
        $this->converter->convert($path);
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::CorruptFile);
    }
});

it('rechaza un PDF sin texto utilizable', function () use (&$tempFiles) {
    $path = PdfDocumentFactory::write('   ', $tempFiles);

    try {
        $this->converter->convert($path);
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::EmptyDocument);
    }
});
