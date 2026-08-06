<?php

declare(strict_types=1);

use App\Actions\GenerateCartAction;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Helper: build a minimal DOCX (valid zip with word/document.xml) that keeps
 * every placeholder inside a single run so TemplateProcessor can replace it.
 *
 * @param  list<string>  $placeholders
 */
function crearTemplateMinimo(string $path, array $placeholders): void
{
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p><w:r><w:t xml:space="preserve">'
        .implode('', array_map(fn (string $k): string => '${'.$k.'}', $placeholders))
        .'</w:t></w:r></w:p></w:body></w:document>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        .'</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        .'</Relationships>';

    $dir = dirname($path);

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();
}

/**
 * Generate a carta with the given values and return the extracted
 * word/document.xml plus the libxml errors of parsing it.
 *
 * @param  array<string, string>  $values
 * @return array{xml: string, errores: array<int, LibXMLError>}
 */
function generarYExtraerDocumento(array $values): array
{
    Storage::fake('local');
    $template = Storage::disk('local')->path('templates/aval-sustentacion.docx');
    crearTemplateMinimo($template, array_keys($values));

    $path = app(GenerateCartAction::class)->handle($template, $values);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($path);

    libxml_use_internal_errors(true);
    simplexml_load_string($xml);
    $errores = libxml_get_errors();
    libxml_clear_errors();

    return ['xml' => $xml, 'errores' => $errores];
}

// -- RF-CA-06: XML escaping of TemplateProcessor values ------------------------

it('genera XML válido cuando los valores tienen &, < y > (RF-CA-06)', function () {
    ['xml' => $xml, 'errores' => $errores] = generarYExtraerDocumento([
        'nombre_estudiante' => 'María José & Laura <Ing.> Pérez',
        'titulo_proyecto' => 'Sistemas & Software',
        'nombre_director' => 'Dra. Ana Rincón',
    ]);

    expect($errores)->toBe([]);
    expect($xml)->toContain('María José &amp; Laura');
    expect($xml)->toContain('&lt;Ing.&gt;');
    expect($xml)->not->toContain('Laura <Ing.>');
});

it('genera XML válido con comillas y apóstrofes escapados (RF-CA-06)', function () {
    ['xml' => $xml, 'errores' => $errores] = generarYExtraerDocumento([
        'nombre_estudiante' => 'Juan "El Grande" O\'Brien',
        'titulo_proyecto' => 'Proyecto de grado',
        'nombre_director' => 'Director',
    ]);

    expect($errores)->toBe([]);
    expect($xml)->toContain('Juan &quot;El Grande&quot; O&#039;Brien');
});

it('mantiene XML válido cuando un valor contiene saltos de línea', function () {
    ['xml' => $xml, 'errores' => $errores] = generarYExtraerDocumento([
        'nombre_estudiante' => "Línea uno\nLínea dos",
        'titulo_proyecto' => 'Título & subtítulo',
        'nombre_director' => 'Director',
    ]);

    expect($errores)->toBe([]);
    expect($xml)->toContain('<w:br/>');
});
