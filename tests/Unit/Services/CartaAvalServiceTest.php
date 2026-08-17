<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\CartaAvalService;
use Illuminate\Support\Carbon;

/**
 * Helper: build an in-memory Entrega model (no DB) with the fields the
 * service reads for habilitación calculation.
 */
function entregaDesarrollo(?string $dueDate, ?string $horaMaxima): Entrega
{
    return new Entrega([
        'due_date' => $dueDate,
        'hora_maxima' => $horaMaxima,
    ]);
}

function servicioCartas(): CartaAvalService
{
    return app(CartaAvalService::class);
}

// -- calcularHabilitacion (D3) ------------------------------------------------

it('habilita cartas cuando now supera due_date más hora_maxima', function () {
    $entregas = collect([entregaDesarrollo(now()->subDay()->toDateString(), '18:00')]);

    $now = now()->subDay()->setTime(18, 0, 1);

    expect(servicioCartas()->calcularHabilitacion($entregas, $now))->toBeTrue();
});

it('habilita cartas con hora_maxima null usando el fin del día', function () {
    $service = servicioCartas();
    $entregas = collect([entregaDesarrollo(now()->subDay()->toDateString(), null)]);

    $now = now()->subDay()->setTime(23, 59, 59);

    expect($service->calcularHabilitacion($entregas, $now))->toBeTrue();
    expect($service->cierreEfectivo($entregas)?->format('H:i:s'))->toBe('23:59:59');
});

it('deshabilita cartas cuando no hay entregas de desarrollo', function () {
    expect(servicioCartas()->calcularHabilitacion(collect(), now()))->toBeFalse();
});

it('deshabilita cartas cuando now es anterior al cierre efectivo', function () {
    $entregas = collect([entregaDesarrollo(now()->addDay()->toDateString(), '18:00')]);

    expect(servicioCartas()->calcularHabilitacion($entregas, now()))->toBeFalse();
});

it('cierreEfectivo usa la entrega desarrollo más tardía', function () {
    $entregas = collect([
        entregaDesarrollo('2026-08-01', '18:00'),
        entregaDesarrollo('2026-08-05', '12:00'),
        entregaDesarrollo('2026-08-10', '20:00'),
    ]);

    expect(servicioCartas()->cierreEfectivo($entregas)?->toDateTimeString())
        ->toBe('2026-08-10 20:00:00');
});

// -- resolverPlaceholders (D1/D2) ---------------------------------------------

it('resuelve placeholders de carta de aval con jurados completos', function () {
    $director = new User(['name' => 'Dra. Ana Rincón']);
    $proyecto = new Proyecto(['title' => 'Sistema de seguimiento']);
    $proyecto->setRelation('director', $director);
    $estudiante = new User(['name' => 'Juan Pérez', 'codigo_estudiante' => 'U0012345']);

    $resultado = servicioCartas()->resolverPlaceholders(
        $proyecto,
        $estudiante,
        'aval',
        ['Jurado Uno', 'Jurado Dos', 'Jurado Tres'],
    );

    expect($resultado['placeholders']['nombre_estudiante'])->toBe('Juan Pérez');
    expect($resultado['placeholders']['codigo_estudiante'])->toBe('U0012345');
    expect($resultado['placeholders']['titulo_proyecto'])->toBe('Sistema de seguimiento');
    expect($resultado['placeholders']['jurado_1_nombre'])->toBe('Jurado Uno');
    expect($resultado['placeholders']['jurado_2_nombre'])->toBe('Jurado Dos');
    expect($resultado['placeholders']['jurado_3_nombre'])->toBe('Jurado Tres');
    expect($resultado['placeholders']['nombre_director'])->toBe('Dra. Ana Rincón');
    expect($resultado['warnings'])->toBe([]);
});

it('deja tabla de jurados vacía y advierte cuando faltan jurados', function () {
    $director = new User(['name' => 'Ana']);
    $proyecto = new Proyecto(['title' => 'X']);
    $proyecto->setRelation('director', $director);
    $estudiante = new User(['name' => 'Juan', 'codigo_estudiante' => '123']);

    $resultado = servicioCartas()->resolverPlaceholders($proyecto, $estudiante, 'aval', ['Jurado Uno']);

    expect($resultado['placeholders']['jurado_1_nombre'])->toBe('Jurado Uno');
    expect($resultado['placeholders']['jurado_2_nombre'])->toBe('');
    expect($resultado['placeholders']['jurado_3_nombre'])->toBe('');
    expect($resultado['warnings'])
        ->toContain('Faltan asignaciones de jurados para presentación final');
});

it('no incluye cédula en los placeholders de la carta de jurados (placeholder literal)', function () {
    $director = new User(['name' => 'Ana']);
    $proyecto = new Proyecto(['title' => 'X']);
    $proyecto->setRelation('director', $director);
    $estudiante = new User(['name' => 'Juan', 'codigo_estudiante' => '12345']);

    $resultado = servicioCartas()->resolverPlaceholders($proyecto, $estudiante, 'jurados');

    expect($resultado['placeholders']['codigo_estudiante'])->toBe('12345');
    expect(array_key_exists('cedula', $resultado['placeholders']))->toBeFalse();
    expect($resultado['warnings'])->toBe([]);
});

it('resuelve nombre del director a vacío cuando el proyecto no tiene director', function () {
    $proyecto = new Proyecto(['title' => 'X']);
    $estudiante = new User(['name' => 'Juan', 'codigo_estudiante' => '123']);

    $resultado = servicioCartas()->resolverPlaceholders($proyecto, $estudiante, 'jurados');

    expect($resultado['placeholders']['nombre_director'])->toBe('');
});

it('incluye ciudad y fecha de generación en los placeholders de la carta de aval', function () {
    Carbon::setTestNow('2026-08-05 10:00:00');

    try {
        $director = new User(['name' => 'Ana']);
        $proyecto = new Proyecto(['title' => 'X']);
        $proyecto->setRelation('director', $director);
        $estudiante = new User(['name' => 'Juan', 'codigo_estudiante' => '12345']);

        $resultado = servicioCartas()->resolverPlaceholders(
            $proyecto,
            $estudiante,
            'aval',
            ['Jurado Uno', 'Jurado Dos', 'Jurado Tres'],
        );

        expect($resultado['placeholders']['ciudad'])->toBe('Bucaramanga');
        expect($resultado['placeholders']['fecha'])->toBe('5 de agosto de 2026');
    } finally {
        Carbon::setTestNow();
    }
});

it('incluye ciudad y fecha de generación en los placeholders de la carta de jurados', function () {
    Carbon::setTestNow('2026-08-05 10:00:00');

    try {
        $director = new User(['name' => 'Ana']);
        $proyecto = new Proyecto(['title' => 'X']);
        $proyecto->setRelation('director', $director);
        $estudiante = new User(['name' => 'Juan', 'codigo_estudiante' => '12345']);

        $resultado = servicioCartas()->resolverPlaceholders($proyecto, $estudiante, 'jurados');

        expect($resultado['placeholders']['ciudad'])->toBe('Bucaramanga');
        expect($resultado['placeholders']['fecha'])->toBe('5 de agosto de 2026');
    } finally {
        Carbon::setTestNow();
    }
});
