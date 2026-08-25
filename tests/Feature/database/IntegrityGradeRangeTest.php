<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Issue #51 — Defect 1: DB-level grade range CHECK constraints
|--------------------------------------------------------------------------
|
| The constraints are PostgreSQL-only (mirroring the existing
| users_role_check migration), so the in-memory SQLite test database cannot
| observe them firing. We verify:
|   - the migration is present and reversible (runs on SQLite),
|   - on PostgreSQL the out-of-range insert is rejected by the constraint.
|
| The acceptance criterion ("DB::table()->insert() out of range fails") is
| asserted on the deployment driver; on SQLite the migration is a documented
| no-op so those tests are skipped there.
*/

it('add_grade_range_check_constraints migration exists and is reversible', function () {
    $migration = include database_path('migrations/2026_08_24_990001_add_grade_range_check_constraints.php');

    expect($migration)->not->toBeNull();
    expect(method_exists($migration, 'up'))->toBeTrue();
    expect(method_exists($migration, 'down'))->toBeTrue();
});

it('grade range check migration runs as a no-op on SQLite', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('No-op behaviour is specific to SQLite.');
    }

    // up() then down() must not throw on SQLite (all statements guarded out).
    $migration = include database_path('migrations/2026_08_24_990001_add_grade_range_check_constraints.php');
    $migration->up();
    $migration->down();

    // The affected tables still exist and accept valid writes.
    expect(Schema::hasTable('evaluaciones'))->toBeTrue();
});

it('rejects an out-of-range grade on PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK constraint is PostgreSQL-only.');
    }

    expect(fn () => DB::table('evaluaciones')->insert([
        'entrega_id' => 1,
        'evaluador_id' => 1,
        'criterio' => 'Rúbrica A',
        'percentage' => 50,
        'grade' => 9, // out of [0, 5]
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects an out-of-range evaluator grade on PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK constraint is PostgreSQL-only.');
    }

    expect(fn () => DB::table('evaluaciones_evaluador')->insert([
        'evaluador_proyecto_id' => 1,
        'nota' => 6, // out of [0, 5]
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
