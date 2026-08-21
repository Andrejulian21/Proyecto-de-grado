<?php

declare(strict_types=1);

use App\Enums\DocumentConversionError;
use App\Exceptions\DocumentConversionException;
use App\Services\Documents\DocumentFormatDetector;
use App\Services\Documents\DocumentMarkdownRouter;
use App\Services\Documents\DocxToMarkdownConverter;
use App\Services\Documents\PdfToMarkdownConverter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\PdfDocumentFactory;

$tempFiles = [];

beforeEach(function () use (&$tempFiles) {
    $this->router = new DocumentMarkdownRouter(
        new DocumentFormatDetector,
        new DocxToMarkdownConverter,
        new PdfToMarkdownConverter,
    );
    $tempFiles = [];
});

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

it('enruta DOCX al conversor existente y produce Markdown compatible', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Documento Word para el pipeline ABET');
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'router_docx_'.uniqid('', true).'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    $tempFiles[] = $path;

    $markdown = $this->router->convert($path, 'avance.docx');

    expect($markdown)->toContain('Documento Word para el pipeline ABET');
});

it('enruta PDF al conversor PDF y produce Markdown compatible', function () use (&$tempFiles) {
    $path = PdfDocumentFactory::write('Documento PDF para el pipeline ABET', $tempFiles);

    $markdown = $this->router->convert($path, 'avance.pdf');

    expect($markdown)->toContain('Documento PDF para el pipeline ABET');
});

it('rechaza un formato no soportado sin intentar IA', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'router_txt_'.uniqid('', true).'.txt';
    file_put_contents($path, 'hola');
    $tempFiles[] = $path;

    try {
        $this->router->convert($path, 'notas.txt', 'text/plain');
        $this->fail('Expected DocumentConversionException');
    } catch (DocumentConversionException $exception) {
        expect($exception->error)->toBe(DocumentConversionError::UnsupportedFormat);
    }
});
