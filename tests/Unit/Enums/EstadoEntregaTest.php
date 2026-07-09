<?php

declare(strict_types=1);

use App\Enums\EstadoEntrega;

test('EstadoEntrega enum has exactly seven cases', function () {
    expect(EstadoEntrega::cases())->toHaveCount(7);
});

test('EstadoEntrega cases are the seven statuses defined in the spec', function () {
    expect(array_map(fn ($c) => $c->name, EstadoEntrega::cases()))->toBe([
        'Creada',
        'Solicitada',
        'Pendiente',
        'Enviada',
        'Revisada',
        'Aprobada',
        'Rechazada',
    ]);
});

test('EstadoEntrega backed values are lowercase strings', function () {
    expect(EstadoEntrega::Creada->value)->toBe('creacion');
    expect(EstadoEntrega::Solicitada->value)->toBe('solicitada');
    expect(EstadoEntrega::Pendiente->value)->toBe('pendiente');
    expect(EstadoEntrega::Enviada->value)->toBe('enviada');
    expect(EstadoEntrega::Revisada->value)->toBe('revisada');
    expect(EstadoEntrega::Aprobada->value)->toBe('aprobada');
    expect(EstadoEntrega::Rechazada->value)->toBe('rechazada');
});

test('EstadoEntrega::values() returns all string values', function () {
    expect(EstadoEntrega::values())->toBe([
        'creacion',
        'solicitada',
        'pendiente',
        'enviada',
        'revisada',
        'aprobada',
        'rechazada',
    ]);
});

test('EstadoEntrega::tryFrom works correctly', function () {
    expect(EstadoEntrega::tryFrom('pendiente'))->toBe(EstadoEntrega::Pendiente);
    expect(EstadoEntrega::tryFrom('solicitada'))->toBe(EstadoEntrega::Solicitada);
    expect(EstadoEntrega::tryFrom('creacion'))->toBe(EstadoEntrega::Creada);
    expect(EstadoEntrega::tryFrom('aprobada'))->toBe(EstadoEntrega::Aprobada);
    expect(EstadoEntrega::tryFrom('inexistente'))->toBeNull();
});

test('EstadoEntrega label returns Spanish display name', function () {
    expect(EstadoEntrega::Creada->label())->toBe('Creada');
    expect(EstadoEntrega::Solicitada->label())->toBe('Solicitada');
    expect(EstadoEntrega::Pendiente->label())->toBe('Pendiente');
    expect(EstadoEntrega::Enviada->label())->toBe('Enviada');
    expect(EstadoEntrega::Revisada->label())->toBe('Revisada');
    expect(EstadoEntrega::Aprobada->label())->toBe('Aprobada');
    expect(EstadoEntrega::Rechazada->label())->toBe('Rechazada');
});
