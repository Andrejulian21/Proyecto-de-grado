<?php

declare(strict_types=1);

use App\Support\Ai\MarkdownTruncator;

it('keeps short markdown untouched without truncation flag', function () {
    $result = app(MarkdownTruncator::class)->truncate('Hola mundo');

    expect($result['wasTruncated'])->toBeFalse()
        ->and($result['text'])->toBe('Hola mundo')
        ->and($result['originalChars'])->toBe(10);
});

it('truncates large markdown to head plus marker plus tail', function () {
    $markdown = str_repeat('a', 40000).str_repeat('b', 25000).str_repeat('c', 25000);

    $result = app(MarkdownTruncator::class)->truncate($markdown);

    expect($result['wasTruncated'])->toBeTrue()
        ->and($result['originalChars'])->toBe(90000)
        ->and(mb_strlen($result['text']))->toBeLessThan(90000)
        ->and($result['text'])->toContain('recortado')
        ->and($result['text'])->toContain('90000');
});
