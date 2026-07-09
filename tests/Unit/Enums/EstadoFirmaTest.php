<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;

test('EstadoFirma enum has exactly five cases', function () {
    expect(EstadoFirma::cases())->toHaveCount(5);
});

test('EstadoFirma cases are the five states defined in spec', function () {
    expect(array_map(fn ($c) => $c->name, EstadoFirma::cases()))->toBe([
        'Pendiente',
        'FirmadaEstudiante',
        'FirmadaDirector',
        'Completada',
        'Sospechosa',
    ]);
});

test('EstadoFirma backed values are correct strings', function () {
    expect(EstadoFirma::Pendiente->value)->toBe('Pendiente');
    expect(EstadoFirma::FirmadaEstudiante->value)->toBe('FirmadaEstudiante');
    expect(EstadoFirma::FirmadaDirector->value)->toBe('FirmadaDirector');
    expect(EstadoFirma::Completada->value)->toBe('Completada');
    expect(EstadoFirma::Sospechosa->value)->toBe('Sospechosa');
});

test('EstadoFirma::tryFrom returns correct case for valid string', function () {
    expect(EstadoFirma::tryFrom('Pendiente'))->toBe(EstadoFirma::Pendiente);
    expect(EstadoFirma::tryFrom('Sospechosa'))->toBe(EstadoFirma::Sospechosa);
});

test('EstadoFirma::tryFrom returns null for unknown string', function () {
    expect(EstadoFirma::tryFrom('Invalida'))->toBeNull();
    expect(EstadoFirma::tryFrom('pendiente'))->toBeNull();
    expect(EstadoFirma::tryFrom(''))->toBeNull();
});

test('EstadoFirma::values() helper returns all string values', function () {
    expect(EstadoFirma::values())->toBe([
        'Pendiente',
        'FirmadaEstudiante',
        'FirmadaDirector',
        'Completada',
        'Sospechosa',
    ]);
});
