<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Services\EntregaPesoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new EntregaPesoService;
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
    ]);
});

/**
 * Helper: seed N entregas in the given phase on the active semester/project.
 * Mirrors how the controller creates them so the service's WHERE matches reality.
 */
function seedEntregas(int $semestreId, int $proyectoId, string $phase, array $percentages): void
{
    foreach ($percentages as $pct) {
        $e = Entrega::create([
            'semester_id' => $semestreId,
            'phase' => $phase,
            'title' => 'Entrega '.$phase,
            'description' => 'x',
            'due_date' => '2026-12-01',
            'status' => 'pendiente',
            'grade_percentage' => $pct,
        ]);
        $e->proyectos()->attach($proyectoId);
    }
}

// -- fasesDelPar -------------------------------------------------------------

it('fasesDelPar returns anteproyecto as independent phase', function () {
    expect($this->service->fasesDelPar('anteproyecto'))
        ->toBe(['anteproyecto']);
});

it('fasesDelPar returns empty for presentacion_anteproyecto (no participation)', function () {
    expect($this->service->fasesDelPar('presentacion_anteproyecto'))
        ->toBe([]);
});

it('fasesDelPar returns desarrollo as independent phase', function () {
    expect($this->service->fasesDelPar('desarrollo'))
        ->toBe(['desarrollo']);
});

it('fasesDelPar returns empty for presentacion_final (no participation)', function () {
    expect($this->service->fasesDelPar('presentacion_final'))
        ->toBe([]);
});

// -- obtenerSumaPar ----------------------------------------------------------

it('obtenerSumaPar returns 0 when no entregas exist for the phase', function () {
    expect($this->service->obtenerSumaPar($this->semestre->id, ['anteproyecto']))
        ->toBe(0.0);
});

it('obtenerSumaPar ignores NULL grade_percentage values', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [null, null, 50.0]);

    expect($this->service->obtenerSumaPar($this->semestre->id, ['anteproyecto']))
        ->toBe(50.0);
});

it('obtenerSumaPar sums only the specified phase', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [40.0]);
    // desarrollo must NOT contribute to anteproyecto sum
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [99.0]);

    expect($this->service->obtenerSumaPar($this->semestre->id, ['anteproyecto']))
        ->toBe(40.0);
});

// -- validarSumaPar: NULL is always allowed -----------------------------------

it('validarSumaPar allows NULL without raising even when par is incomplete', function () {
    // No prior entregas for Par 1. Passing NULL must NOT block.
    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', null);

    expect(true)->toBeTrue();
});

it('validarSumaPar allows NULL even when other entregas would push the pair over 100%', function () {
    // Pair 1 already sums to 70.
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [70.0]);

    // Passing NULL must NOT raise — the "would exceed 100%" only applies to NOT NULL values.
    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', null);

    expect(true)->toBeTrue();
});

// -- validarSumaPar: bloquea suma > 100 --------------------------------------

it('validarSumaPar raises when proposal would make phase sum differ from 100', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [70.0]);

    // Phase already has entries → completeness fires: sum must be exactly 100
    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 40.0);
})->throws(ValidationException::class, 'exactamente 100%');

// -- validarSumaPar: completitud = exacto 100 --------------------------------

it('validarSumaPar raises when completing the phase would overshoot 100%', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [50.0]);
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [40.0]);

    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 20.0);
})->throws(ValidationException::class, 'exactamente 100%');

it('validarSumaPar allows completing the phase at exactly 100%', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [40.0]);

    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 60.0);

    expect(true)->toBeTrue();
});

it('validarSumaPar allows 50 + 50 even with no existing entregas', function () {
    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 50.0);

    expect(true)->toBeTrue();
});

it('validarSumaPar allows a phase where no entregas have % yet', function () {
    // One entrega in desarrollo at 50. No entregas in anteproyecto.
    // Adding 50 in anteproyecto → 50 (anteproyecto only). OK.
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [50.0]);

    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 50.0);

    expect(true)->toBeTrue();
});

// -- desarrollo phase --------------------------------------------------------

it('validarSumaPar applies the same rules to desarrollo phase', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [60.0]);
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [30.0]);

    // 60 + 30 = 90, adding 20 → 110 → rejected
    $this->service->validarSumaPar($this->semestre->id, 'desarrollo', 20.0);
})->throws(ValidationException::class, 'exactamente 100%');

// -- Cross-phase isolation ---------------------------------------------------

it('validarSumaPar in anteproyecto ignores desarrollo entregas', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [90.0]);

    // anteproyecto has no prior entregas → adding 40 is OK
    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 40.0);

    expect(true)->toBeTrue();
});

it('validarSumaPar in desarrollo ignores anteproyecto entregas', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [90.0]);

    // desarrollo has no prior entregas → adding 40 is OK
    $this->service->validarSumaPar($this->semestre->id, 'desarrollo', 40.0);

    expect(true)->toBeTrue();
});
