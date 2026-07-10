<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;

test('EstadoInvitacionEvaluador enum tiene exactamente 3 casos', function () {
    expect(EstadoInvitacionEvaluador::cases())->toHaveCount(3);
});

test('EstadoInvitacionEvaluador cases son los 3 estados de invitacion', function () {
    expect(array_map(fn ($c) => $c->name, EstadoInvitacionEvaluador::cases()))->toBe([
        'Pendiente',
        'Aceptada',
        'Rechazada',
    ]);
});

test('EstadoInvitacionEvaluador backed values son strings', function () {
    expect(EstadoInvitacionEvaluador::Pendiente->value)->toBe('Pendiente');
    expect(EstadoInvitacionEvaluador::Aceptada->value)->toBe('Aceptada');
    expect(EstadoInvitacionEvaluador::Rechazada->value)->toBe('Rechazada');
});

test('EstadoInvitacionEvaluador::values() retorna todos los valores', function () {
    expect(EstadoInvitacionEvaluador::values())->toBe([
        'Pendiente',
        'Aceptada',
        'Rechazada',
    ]);
});

test('EstadoInvitacionEvaluador::tryFrom funciona correctamente', function () {
    expect(EstadoInvitacionEvaluador::tryFrom('Pendiente'))->toBe(EstadoInvitacionEvaluador::Pendiente);
    expect(EstadoInvitacionEvaluador::tryFrom('Aceptada'))->toBe(EstadoInvitacionEvaluador::Aceptada);
    expect(EstadoInvitacionEvaluador::tryFrom('inexistente'))->toBeNull();
});

test('EstadoInvitacionEvaluador label retorna nombre legible', function () {
    expect(EstadoInvitacionEvaluador::Pendiente->label())->toBe('Pendiente');
    expect(EstadoInvitacionEvaluador::Aceptada->label())->toBe('Aceptada');
    expect(EstadoInvitacionEvaluador::Rechazada->label())->toBe('Rechazada');
});
