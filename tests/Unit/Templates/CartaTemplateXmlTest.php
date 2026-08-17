<?php

declare(strict_types=1);

use App\Actions\GenerateCartAction;

/**
 * Regression guard for the Carta 1 template corruption (nested `<w:r>` runs
 * inside `<w:r>` made Word report "error al abrir" on download).
 *
 * The templates live in `storage/app/templates/` (gitignored), so these tests
 * skip when the files are absent (e.g. fresh CI checkout). When present they
 * enforce Word-compatible OOXML on the template AND on the generated output:
 * well-formed XML, no run nesting, balanced runs per paragraph, and no
 * leftover `${placeholder}` after generation (RF-CA-06).
 */

/**
 * @return array{wellformed: bool, errores: int, maxDepth: int, nested: int, violations: list<string>}
 */
function validarEstructuraRuns(string $xml): array
{
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument;
    $wellformed = $doc->loadXML($xml);
    $errores = count(libxml_get_errors());
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    preg_match_all('/<w:p[ >]|<\/w:p>|<w:r[ >]|<\/w:r>/', $xml, $m, PREG_OFFSET_CAPTURE);

    $depth = 0;
    $maxDepth = 0;
    $nested = 0;
    $violations = [];

    foreach ($m[0] as [$tok, $pos]) {
        if (str_starts_with($tok, '<w:p')) {
            if ($depth !== 0) {
                $violations[] = "paragraph-boundary-with-open-runs@{$pos}";
            }
        } elseif ($tok === '</w:p>') {
            if ($depth !== 0) {
                $violations[] = "paragraph-close-with-open-runs@{$pos}";
            }
        } elseif (str_starts_with($tok, '<w:r')) {
            if ($depth >= 1) {
                $nested++;
                $violations[] = "nested-run@{$pos}";
            }
            $depth++;
            $maxDepth = max($maxDepth, $depth);
        } else {
            $depth--;
            if ($depth < 0) {
                $violations[] = "close-without-open@{$pos}";
            }
        }
    }

    if ($depth !== 0) {
        $violations[] = 'unbalanced-runs-at-eof';
    }

    return compact('wellformed', 'errores', 'maxDepth', 'nested', 'violations');
}

function documentoXmlDelDocx(string $path): ?string
{
    if (! is_file($path)) {
        return null;
    }

    $zip = new ZipArchive;

    if ($zip->open($path) !== true) {
        return null;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    return $xml === false ? null : $xml;
}

function skipSinTemplate(string $path): void
{
    if (! is_file($path)) {
        test()->markTestSkipped("Template no presente (gitignored): {$path}");
    }
}

// -- Template en disco ---------------------------------------------------------

it('template aval-sustentacion es XML bien formado sin runs anidados y con los 7 placeholders', function () {
    $path = storage_path('app/templates/aval-sustentacion.docx');
    skipSinTemplate($path);

    $xml = documentoXmlDelDocx($path);
    expect($xml)->not->toBeNull();

    $r = validarEstructuraRuns($xml);
    expect($r['wellformed'])->toBeTrue();
    expect($r['errores'])->toBe(0);
    expect($r['nested'])->toBe(0);
    expect($r['maxDepth'])->toBe(1);
    expect($r['violations'])->toBe([]);

    foreach (['nombre_estudiante', 'codigo_estudiante', 'titulo_proyecto',
        'jurado_1_nombre', 'jurado_2_nombre', 'jurado_3_nombre', 'nombre_director'] as $ph) {
        expect($xml)->toContain('${'.$ph.'}');
    }
});

it('template carta-jurados es XML bien formado sin runs anidados', function () {
    $path = storage_path('app/templates/carta-jurados.docx');
    skipSinTemplate($path);

    $xml = documentoXmlDelDocx($path);
    expect($xml)->not->toBeNull();

    $r = validarEstructuraRuns($xml);
    expect($r['wellformed'])->toBeTrue();
    expect($r['errores'])->toBe(0);
    expect($r['nested'])->toBe(0);
    expect($r['maxDepth'])->toBe(1);
    expect($r['violations'])->toBe([]);
});

// -- Salida generada (RF-CA-06) -------------------------------------------------

it('genera aval-sustentacion con caracteres especiales sin runs anidados ni placeholders sobrantes', function () {
    $template = storage_path('app/templates/aval-sustentacion.docx');
    skipSinTemplate($template);

    $out = app(GenerateCartAction::class)->handle($template, [
        'nombre_estudiante' => 'María José & Laura <Ing.> "La Grande" O\'Brien',
        'codigo_estudiante' => 'U0012345',
        'titulo_proyecto' => 'Sistema de Gestión & Seguimiento <2026> "Fase II"',
        'jurado_1_nombre' => 'Dr. Carlos & Pérez',
        'jurado_2_nombre' => 'Ing. Ana <TIC>',
        'jurado_3_nombre' => 'Mg. Luis "El Sabio"',
        'nombre_director' => 'Dra. María del Carmen & Asociados',
    ]);

    try {
        $xml = documentoXmlDelDocx($out);
        expect($xml)->not->toBeNull();

        $r = validarEstructuraRuns($xml);
        expect($r['wellformed'])->toBeTrue();
        expect($r['errores'])->toBe(0);
        expect($r['nested'])->toBe(0);
        expect($r['maxDepth'])->toBe(1);
        expect($r['violations'])->toBe([]);
        expect($xml)->not->toContain('${');
        expect($xml)->toContain('&amp;');
        expect($xml)->toContain('&lt;');
        expect($xml)->toContain('&quot;');
    } finally {
        @unlink($out);
    }
});

it('genera carta-jurados con caracteres especiales sin runs anidados ni placeholders sobrantes', function () {
    $template = storage_path('app/templates/carta-jurados.docx');
    skipSinTemplate($template);

    $out = app(GenerateCartAction::class)->handle($template, [
        'nombre_estudiante' => 'María José & Laura <Ing.>',
        'codigo_estudiante' => 'U0012345',
        'titulo_proyecto' => 'Sistema & Seguimiento <2026>',
        'nombre_director' => 'Dra. María del Carmen',
    ]);

    try {
        $xml = documentoXmlDelDocx($out);
        expect($xml)->not->toBeNull();

        $r = validarEstructuraRuns($xml);
        expect($r['wellformed'])->toBeTrue();
        expect($r['errores'])->toBe(0);
        expect($r['nested'])->toBe(0);
        expect($r['maxDepth'])->toBe(1);
        expect($r['violations'])->toBe([]);
        expect($xml)->not->toContain('${');
        expect($xml)->toContain('&amp;');
        expect($xml)->toContain('&lt;');
    } finally {
        @unlink($out);
    }
});