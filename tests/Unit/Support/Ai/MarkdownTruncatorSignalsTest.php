<?php

declare(strict_types=1);

use App\Support\Ai\MarkdownTruncator;

it('keeps the signal-relevant section and lists kept and omitted sections', function () {
    $make = function (string $heading, string $keyword): string {
        $body = str_repeat("Contenido de {$keyword} del anteproyecto. ", 600);

        return "# {$heading}\n\n{$body}\n";
    };

    $markdown = $make('Planteamiento', 'planteamiento del problema')
        .$make('Objetivos', 'objetivos generales especificos')
        .$make('Relleno historico', 'relleno cronologia anexos');

    expect(mb_strlen($markdown))->toBeGreaterThan(MarkdownTruncator::MAX_CHARS);

    $result = app(MarkdownTruncator::class)->truncate($markdown, ['objetivos']);

    expect($result['wasTruncated'])->toBeTrue()
        ->and($result['text'])->toContain('Objetivos')
        ->and($result['text'])->toContain('recortado')
        ->and($result['text'])->toContain('conservadas')
        ->and($result['text'])->toContain('omitidas')
        ->and(mb_strlen($result['text']))->toBeLessThanOrEqual(MarkdownTruncator::MAX_CHARS);
});

it('falls back to head plus tail when no signals are given', function () {
    $markdown = str_repeat('a', 40000).str_repeat('b', 25000).str_repeat('c', 25000);

    $result = app(MarkdownTruncator::class)->truncate($markdown);

    expect($result['wasTruncated'])->toBeTrue()
        ->and($result['text'])->toContain('recortado')
        ->and($result['text'])->not->toContain('conservadas');
});

it('derives normalized signals without stopwords or duplicates', function () {
    $signals = MarkdownTruncator::signalsFromTexts(
        'Los Objetivos ESPECÍFICOS de la entrega',
        'Planteamiento del problema y objetivos',
    );

    expect($signals)->toContain('objetivos')
        ->and($signals)->toContain('específicos')
        ->and($signals)->toContain('planteamiento')
        ->and($signals)->toContain('problema')
        ->and($signals)->not->toContain('los')
        ->and($signals)->not->toContain('de')
        ->and($signals)->not->toContain('la')
        ->and($signals)->not->toContain('y')
        ->and($signals)->not->toContain('del')
        ->and(array_count_values($signals)['objetivos'] ?? 0)->toBe(1);
});

it('snaps an oversized relevant section at a word boundary within budget', function () {
    $markdown = '# Objetivos'."\n\n".str_repeat('objetivos ', 20000)."\n".'# Anexos'."\n\n".str_repeat('anexo cronologia ', 5000);

    expect(mb_strlen($markdown))->toBeGreaterThan(MarkdownTruncator::MAX_CHARS);

    $result = app(MarkdownTruncator::class)->truncate($markdown, ['objetivos']);

    expect($result['wasTruncated'])->toBeTrue()
        ->and($result['text'])->toContain("objetivos\n\n[…recortado por secciones")
        ->and(mb_strlen($result['text']))->toBeLessThanOrEqual(MarkdownTruncator::MAX_CHARS);
});
it('prefers the highest-overlap sections when several signals match', function () {
    $make = function (string $heading, string $body): string {
        return "# {$heading}\n\n".str_repeat("{$body} ", 600)."\n";
    };

    $markdown = $make('Marco teorico', 'marco teorico antecedentes autores')
        .$make('Metodologia', 'metodologia enfoque alcance cronograma')
        .$make('Objetivos', 'objetivos metodologia alcance del proyecto');

    expect(mb_strlen($markdown))->toBeGreaterThan(MarkdownTruncator::MAX_CHARS);

    $result = app(MarkdownTruncator::class)->truncate($markdown, ['metodologia', 'alcance', 'objetivos']);

    expect($result['wasTruncated'])->toBeTrue()
        ->and($result['text'])->toContain('Objetivos')
        ->and($result['text'])->toContain('Metodologia')
        ->and($result['text'])->toContain('conservadas')
        ->and($result['text'])->toContain('omitidas');
});
