<?php

declare(strict_types=1);

use App\Enums\EstadoProyecto;

test('EstadoProyecto enum tiene exactamente 4 casos', function () {
    expect(EstadoProyecto::cases())->toHaveCount(4);
});

test('EstadoProyecto cases son los 4 estados del ciclo de vida', function () {
    expect(array_map(fn ($c) => $c->name, EstadoProyecto::cases()))->toBe([
        'EnCurso',
        'EnRiesgo',
        'Incumplimiento',
        'Completado',
    ]);
});

test('EstadoProyecto backed values son strings en español', function () {
    expect(EstadoProyecto::EnCurso->value)->toBe('en_curso');
    expect(EstadoProyecto::EnRiesgo->value)->toBe('en_riesgo');
    expect(EstadoProyecto::Incumplimiento->value)->toBe('incumplimiento');
    expect(EstadoProyecto::Completado->value)->toBe('completado');
});

test('EstadoProyecto::values() retorna todos los valores', function () {
    expect(EstadoProyecto::values())->toBe([
        'en_curso',
        'en_riesgo',
        'incumplimiento',
        'completado',
    ]);
});

test('EstadoProyecto::tryFrom funciona correctamente', function () {
    expect(EstadoProyecto::tryFrom('en_curso'))->toBe(EstadoProyecto::EnCurso);
    expect(EstadoProyecto::tryFrom('completado'))->toBe(EstadoProyecto::Completado);
    expect(EstadoProyecto::tryFrom('inexistente'))->toBeNull();
});

test('EstadoProyecto label retorna nombre legible en español', function () {
    expect(EstadoProyecto::EnCurso->label())->toBe('En Curso');
    expect(EstadoProyecto::EnRiesgo->label())->toBe('En Riesgo');
    expect(EstadoProyecto::Incumplimiento->label())->toBe('Incumplimiento');
    expect(EstadoProyecto::Completado->label())->toBe('Completado');
});
