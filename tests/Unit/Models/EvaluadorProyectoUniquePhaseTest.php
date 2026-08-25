<?php

declare(strict_types=1);

use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Issue #51 — Defect 2: UNIQUE (proyecto_id, evaluador_id, fase)
|--------------------------------------------------------------------------
|
| A project MAY assign the same evaluator once per phase, so the legacy
| (proyecto_id, evaluador_id) UNIQUE (added before `fase` existed) is
| replaced by (proyecto_id, evaluador_id, fase). These tests run on the
| in-memory SQLite schema, which now carries the corrected constraint.
*/

it('allows the same evaluator on the same project in two different phases', function () {
    $proyecto = Proyecto::factory()->create();
    $evaluador = User::factory()->external()->create();

    EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'fase' => 'presentacion_anteproyecto',
    ]);

    EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'fase' => 'presentacion_final',
    ]);

    expect(EvaluadorProyecto::query()->count())->toBe(2);
});

it('rejects the same evaluator twice on the same project in the same phase', function () {
    $proyecto = Proyecto::factory()->create();
    $evaluador = User::factory()->external()->create();

    EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'fase' => 'presentacion_anteproyecto',
    ]);

    expect(fn () => EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'fase' => 'presentacion_anteproyecto',
    ]))->toThrow(QueryException::class);
});

it('allows a different evaluator on the same project in the same phase', function () {
    $proyecto = Proyecto::factory()->create();
    $evaluadorA = User::factory()->external()->create();
    $evaluadorB = User::factory()->external()->create();

    EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluadorA->id,
        'fase' => 'presentacion_anteproyecto',
    ]);
    EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluadorB->id,
        'fase' => 'presentacion_anteproyecto',
    ]);

    expect(EvaluadorProyecto::query()->count())->toBe(2);
});
