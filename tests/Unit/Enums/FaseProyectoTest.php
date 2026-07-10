<?php

declare(strict_types=1);

use App\Enums\FaseProyecto;

test('FaseProyecto enum tiene exactamente 4 casos', function () {
    expect(FaseProyecto::cases())->toHaveCount(4);
});

test('FaseProyecto cases son las 4 fases del proyecto', function () {
    expect(array_map(fn ($c) => $c->name, FaseProyecto::cases()))->toBe([
        'Anteproyecto',
        'PresentacionAnteproyecto',
        'Desarrollo',
        'PresentacionFinal',
    ]);
});

test('FaseProyecto backed values son strings en español', function () {
    expect(FaseProyecto::Anteproyecto->value)->toBe('anteproyecto');
    expect(FaseProyecto::PresentacionAnteproyecto->value)->toBe('presentacion_anteproyecto');
    expect(FaseProyecto::Desarrollo->value)->toBe('desarrollo');
    expect(FaseProyecto::PresentacionFinal->value)->toBe('presentacion_final');
});

test('FaseProyecto::values() retorna todos los valores', function () {
    expect(FaseProyecto::values())->toBe([
        'anteproyecto',
        'presentacion_anteproyecto',
        'desarrollo',
        'presentacion_final',
    ]);
});

test('FaseProyecto::tryFrom funciona correctamente', function () {
    expect(FaseProyecto::tryFrom('anteproyecto'))->toBe(FaseProyecto::Anteproyecto);
    expect(FaseProyecto::tryFrom('desarrollo'))->toBe(FaseProyecto::Desarrollo);
    expect(FaseProyecto::tryFrom('inexistente'))->toBeNull();
});

test('FaseProyecto label retorna nombre legible en español', function () {
    expect(FaseProyecto::Anteproyecto->label())->toBe('Anteproyecto');
    expect(FaseProyecto::PresentacionAnteproyecto->label())->toBe('Presentación Anteproyecto');
    expect(FaseProyecto::Desarrollo->label())->toBe('Desarrollo');
    expect(FaseProyecto::PresentacionFinal->label())->toBe('Presentación Final');
});

test('FaseProyecto::next() retorna la fase siguiente o null', function () {
    expect(FaseProyecto::Anteproyecto->next())->toBe(FaseProyecto::PresentacionAnteproyecto);
    expect(FaseProyecto::PresentacionAnteproyecto->next())->toBe(FaseProyecto::Desarrollo);
    expect(FaseProyecto::Desarrollo->next())->toBe(FaseProyecto::PresentacionFinal);
    expect(FaseProyecto::PresentacionFinal->next())->toBeNull();
});
