<?php

declare(strict_types=1);

use App\Models\Proyecto;
use App\Models\Semestre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Issue #51 — Defect 3: sequential project code generation
|--------------------------------------------------------------------------
|
| The PG-xxxx code is computed with count()+1 inside the creating hook. Two
| concurrent creations for the same semester could read the same count and
| produce the same code, tripping the `code` UNIQUE constraint. On
| PostgreSQL the hook now takes a semester-keyed advisory lock and
| ProyectoController::store() wraps the creation in a transaction. These
| tests verify the refactored logic still produces distinct sequential
| codes (and that it works inside a DB::transaction).
*/

it('generates distinct sequential codes for two projects in the same semester', function () {
    $semestre = Semestre::factory()->create(['name' => '2026-1']);

    $a = Proyecto::factory()->create(['semester_id' => $semestre->id]);
    $b = Proyecto::factory()->create(['semester_id' => $semestre->id]);

    expect($a->code)->toMatch('/^PG-20261\d{3}$/');
    expect($b->code)->toMatch('/^PG-20261\d{3}$/');
    expect($a->code)->not->toBe($b->code);
});

it('generates codes that increment sequentially within a semester', function () {
    $semestre = Semestre::factory()->create(['name' => '2026-1']);

    $a = Proyecto::factory()->create(['semester_id' => $semestre->id]);
    $b = Proyecto::factory()->create(['semester_id' => $semestre->id]);
    $c = Proyecto::factory()->create(['semester_id' => $semestre->id]);

    $numbers = collect([$a, $b, $c])
        ->map(fn (Proyecto $p) => (int) substr($p->code, -3))
        ->sort()
        ->values();

    expect($numbers->all())->toBe([1, 2, 3]);
});

it('generates a code when the project is created inside a DB transaction', function () {
    $semestre = Semestre::factory()->create(['name' => '2026-2']);

    $proyecto = DB::transaction(
        fn () => Proyecto::factory()->create(['semester_id' => $semestre->id])
    );

    expect($proyecto->code)->toMatch('/^PG-20262\d{3}$/');
});
