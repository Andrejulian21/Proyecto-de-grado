<?php

declare(strict_types=1);

use App\Enums\DocumentFormat;
use App\Services\Documents\DocumentFormatDetector;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\PdfDocumentFactory;

$tempFiles = [];

beforeEach(function () use (&$tempFiles) {
    $this->detector = new DocumentFormatDetector;
    $tempFiles = [];
});

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

it('detecta PDF por magic bytes aunque la extension sea docx', function () use (&$tempFiles) {
    $path = PdfDocumentFactory::write('Texto PDF', $tempFiles, '.docx');

    expect($this->detector->detect($path, 'informe.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->toBe(DocumentFormat::Pdf);
});

it('detecta DOCX por estructura ZIP de Word', function () use (&$tempFiles) {
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Hola');
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'detect_docx_'.uniqid('', true).'.bin';
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    $tempFiles[] = $path;

    expect($this->detector->detect($path, 'archivo.bin', 'application/octet-stream'))
        ->toBe(DocumentFormat::Docx);
});

it('rechaza un zip que no es DOCX', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plain_zip_'.uniqid('', true).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('readme.txt', 'no es word');
    $zip->close();
    $tempFiles[] = $path;

    expect($this->detector->detect($path, 'readme.zip'))->toBe(DocumentFormat::Unsupported);
});

it('rechaza bytes que no son PDF ni DOCX aunque terminen en .pdf', function () use (&$tempFiles) {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fake_'.uniqid('', true).'.pdf';
    file_put_contents($path, 'esto no es un pdf');
    $tempFiles[] = $path;

    expect($this->detector->detect($path, 'falso.pdf', 'application/pdf'))
        ->toBe(DocumentFormat::Unsupported);
});
