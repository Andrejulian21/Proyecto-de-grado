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
    $this->service = new EntregaPesoService();
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
            'proyecto_id' => $proyectoId,
            'semester_id' => $semestreId,
            'phase' => $phase,
            'title' => 'Entrega ' . $phase,
            'description' => 'x',
            'due_date' => '2026-12-01',
            'status' => 'pendiente',
            'grade_percentage' => $pct,
        ]);
        $e->proyectos()->attach($proyectoId);
    }
}

// -- fasesDelPar -------------------------------------------------------------

it('fasesDelPar returns Par 1 for anteproyecto phases', function () {
    expect($this->service->fasesDelPar('anteproyecto'))
        ->toBe(['anteproyecto', 'presentacion_anteproyecto']);

    expect($this->service->fasesDelPar('presentacion_anteproyecto'))
        ->toBe(['anteproyecto', 'presentacion_anteproyecto']);
});

it('fasesDelPar returns Par 2 for desarrollo/final phases', function () {
    expect($this->service->fasesDelPar('desarrollo'))
        ->toBe(['desarrollo', 'presentacion_final']);

    expect($this->service->fasesDelPar('presentacion_final'))
        ->toBe(['desarrollo', 'presentacion_final']);
});

// -- obtenerSumaPar ----------------------------------------------------------

it('obtenerSumaPar returns 0 when no entregas exist for the pair', function () {
    expect($this->service->obtenerSumaPar($this->semestre->id, ['anteproyecto', 'presentacion_anteproyecto']))
        ->toBe(0.0);
});

it('obtenerSumaPar ignores NULL grade_percentage values', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [null, null, 50.0]);

    expect($this->service->obtenerSumaPar($this->semestre->id, ['anteproyecto', 'presentacion_anteproyecto']))
        ->toBe(50.0);
});

it('obtenerSumaPar sums only the two phases of the pair', function () {
    // Par 1 entregas
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [40.0]);
    seedEntregas($this->semestre->id, $this->proyecto->id, 'presentacion_anteproyecto', [30.0]);
    // Par 2 entrega (must NOT contribute to Par 1 sum)
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [99.0]);

    expect($this->service->obtenerSumaPar($this->semestre->id, ['anteproyecto', 'presentacion_anteproyecto']))
        ->toBe(70.0);
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

it('validarSumaPar raises when NOT NULL proposal would exceed 100%', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [70.0]);

    $this->service->validarSumaPar($this->semestre->id, 'presentacion_anteproyecto', 40.0);
})->throws(ValidationException::class, 'superaría el 100%');

// -- validarSumaPar: completitud = exacto 100 --------------------------------

it('validarSumaPar raises when completing the pair would overshoot 100%', function () {
    // Pair 1 has 50 (anteproyecto) + 40 (presentacion_anteproyecto) = 90.
    // New entrega in presentacion_anteproyecto at 20 would make 110 → must reject.
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [50.0]);
    seedEntregas($this->semestre->id, $this->proyecto->id, 'presentacion_anteproyecto', [40.0]);

    $this->service->validarSumaPar($this->semestre->id, 'presentacion_anteproyecto', 20.0);
})->throws(ValidationException::class, 'exactamente 100%');

it('validarSumaPar allows completing the pair at exactly 100%', function () {
    // Par 1 has 40 in anteproyecto. Adding 60 in presentacion_anteproyecto → 100. OK.
    seedEntregas($this->semestre->id, $this->proyecto->id, 'anteproyecto', [40.0]);

    $this->service->validarSumaPar($this->semestre->id, 'presentacion_anteproyecto', 60.0);

    expect(true)->toBeTrue();
});

it('validarSumaPar allows 50 + 50 even with no existing entregas', function () {
    // No prior entregas. 50 must be allowed because the pair is still incomplete.
    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 50.0);

    expect(true)->toBeTrue();
});

it('validarSumaPar allows a par where one phase still has no entregas with %', function () {
    // One entrega in presentacion_anteproyecto at 50. No entregas in anteproyecto.
    // Adding 50 in anteproyecto → 100. Pair is now complete and exact. OK.
    seedEntregas($this->semestre->id, $this->proyecto->id, 'presentacion_anteproyecto', [50.0]);

    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 50.0);

    expect(true)->toBeTrue();
});

// -- Par 2 (desarrollo + presentacion_final) ---------------------------------

it('validarSumaPar applies the same rules to Par 2 (desarrollo + presentacion_final)', function () {
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [60.0]);
    seedEntregas($this->semestre->id, $this->proyecto->id, 'presentacion_final', [30.0]);

    // Both phases of Par 2 already have NOT NULL weights (60 + 30 = 90), so the
    // completeness rule fires FIRST (RF-ENT-04): the pair must sum exactly 100.
    // Adding 20 pushes it to 110 → rejected with the completeness message.
    $this->service->validarSumaPar($this->semestre->id, 'presentacion_final', 20.0);
})->throws(ValidationException::class, 'exactamente 100%');

// -- Cross-pair isolation -----------------------------------------------------

it('validarSumaPar in Par 1 ignores Par 2 entregas', function () {
    // Par 2 sum = 90. Adding 40 in anteproyecto (Par 1, no prior entregas) → 40 in Par 1. OK.
    seedEntregas($this->semestre->id, $this->proyecto->id, 'desarrollo', [50.0]);
    seedEntregas($this->semestre->id, $this->proyecto->id, 'presentacion_final', [40.0]);

    $this->service->validarSumaPar($this->semestre->id, 'anteproyecto', 40.0);

    expect(true)->toBeTrue();
});
