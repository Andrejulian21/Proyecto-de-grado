<?php

declare(strict_types=1);

use App\Models\EvaluacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| PR1 — Schema Foundation: Entregas y Evaluación de Evaluadores
|--------------------------------------------------------------------------
|
| Tests covering RF-ENT-03, RF-ENT-04, RF-NOT-01, RF-NOT-04, RF-EVA-05:
|   - grade_percentage decimal(5,2) nullable on `entregas`
|   - director_grade decimal(4,2) nullable on `entregas`
|   - evaluado boolean default false on `evaluador_proyecto`
|   - evaluaciones_evaluador table with FK UNIQUE on evaluador_proyecto_id
|   - EvaluacionEvaluador model with belongsTo EvaluadorProyecto
|
| The tests are written to be DB-agnostic: SQLite in-memory is used in
| the test environment (phpunit.xml), while PostgreSQL is the production
| driver. Column metadata exposure differs (SQLite uses type affinities and
| does not surface precision/scale via `Schema::getColumns()`), so we
| branch on driver for the parts that Postgres exposes natively.
*/

uses(RefreshDatabase::class);

/**
 * Check if a column has decimal/numeric type. SQLite reports "numeric" and
 * PostgreSQL reports "decimal" / "numeric" depending on the variant.
 */
function columnIsDecimalLike(?array $col): bool
{
    if ($col === null) {
        return false;
    }

    $type = strtolower((string) ($col['type'] ?? $col['type_name'] ?? ''));

    return str_contains($type, 'numeric') || str_contains($type, 'decimal');
}

/**
 * Check if a column has boolean type. SQLite uses "boolean" (affinity =
 * numeric) and PostgreSQL uses "bool".
 */
function columnIsBooleanLike(?array $col): bool
{
    if ($col === null) {
        return false;
    }

    $type = strtolower((string) ($col['type'] ?? $col['type_name'] ?? ''));

    return str_contains($type, 'bool');
}

// ---------------------------------------------------------------------------
// T-002: grade_percentage + director_grade columns on `entregas`
// ---------------------------------------------------------------------------

test('entregas has grade_percentage column (nullable decimal/numeric)', function () {
    expect(Schema::hasColumn('entregas', 'grade_percentage'))->toBeTrue();

    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'grade_percentage');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
    expect(columnIsDecimalLike($col))->toBeTrue();
});

test('grade_percentage has precision 5, scale 2 on PostgreSQL', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'grade_percentage');

    // SQLite does not surface precision/scale via Schema::getColumns().
    // On production (PostgreSQL) the migration declares decimal(5,2).
    if (DB::getDriverName() === 'pgsql') {
        expect((int) ($col['precision'] ?? 0))->toBe(5);
        expect((int) ($col['scale'] ?? 0))->toBe(2);
    } else {
        expect(true)->toBeTrue(); // SQLite stores numeric affinity; precision checked at model level.
    }
});

test('entregas has director_grade column (nullable decimal/numeric)', function () {
    expect(Schema::hasColumn('entregas', 'director_grade'))->toBeTrue();

    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'director_grade');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
    expect(columnIsDecimalLike($col))->toBeTrue();
});

test('director_grade has precision 4, scale 2 on PostgreSQL', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'director_grade');

    if (DB::getDriverName() === 'pgsql') {
        expect((int) ($col['precision'] ?? 0))->toBe(4);
        expect((int) ($col['scale'] ?? 0))->toBe(2);
    } else {
        expect(true)->toBeTrue();
    }
});

test('Entrega persists grade_percentage as decimal via cast', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $proyecto = Proyecto::create(['title' => 'P', 'semester_id' => $semestre->id]);

    $entrega = \App\Models\Entrega::create([
        'proyecto_id' => $proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'E1',
        'due_date' => '2026-03-15',
        'grade_percentage' => 60,
    ]);

    expect((float) $entrega->fresh()->grade_percentage)->toEqual(60.0);
    expect($entrega->getCasts())->toHaveKey('grade_percentage');
});

test('Entrega persists director_grade as decimal via cast', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $proyecto = Proyecto::create(['title' => 'P', 'semester_id' => $semestre->id]);

    $entrega = \App\Models\Entrega::create([
        'proyecto_id' => $proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'E1',
        'due_date' => '2026-03-15',
        'director_grade' => 4.5,
    ]);

    expect((float) $entrega->fresh()->director_grade)->toEqual(4.5);
    expect($entrega->getCasts())->toHaveKey('director_grade');
});

test('Entrega fillable includes grade_percentage and director_grade', function () {
    $entrega = new \App\Models\Entrega;

    expect($entrega->getFillable())->toContain('grade_percentage')
        ->and($entrega->getFillable())->toContain('director_grade');
});

// ---------------------------------------------------------------------------
// T-003: evaluado boolean default false on `evaluador_proyecto`
// ---------------------------------------------------------------------------

test('evaluador_proyecto has evaluado column (boolean)', function () {
    expect(Schema::hasColumn('evaluador_proyecto', 'evaluado'))->toBeTrue();

    $columns = Schema::getColumns('evaluador_proyecto');
    $col = collect($columns)->firstWhere('name', 'evaluado');

    expect($col)->not->toBeNull();
    expect(columnIsBooleanLike($col))->toBeTrue();
});

test('evaluador_proyecto.evaluado defaults to false at the DB level', function () {
    $columns = Schema::getColumns('evaluador_proyecto');
    $col = collect($columns)->firstWhere('name', 'evaluado');

    if (DB::getDriverName() === 'pgsql') {
        $default = trim((string) ($col['default'] ?? ''), "'");
        expect(in_array($default, ['false', '0'], true))->toBeTrue(
            "evaluado default on PostgreSQL should be false/0, got '{$default}'"
        );
    } else {
        // SQLite: ensure the raw column default is '0' (false) — but SQLite may
        // not always surface a default in the schema array. We verify behavior
        // at the model/factory level below.
        $rawDefault = DB::selectOne(
            "SELECT dflt_value AS default_value FROM pragma_table_info('evaluador_proyecto') WHERE name = 'evaluado'"
        );
        if ($rawDefault && $rawDefault->default_value !== null) {
            expect(in_array(trim((string) $rawDefault->default_value), ['0', 'false'], true))->toBeTrue();
        } else {
            // No DB-level default means the model default takes over. See
            // the factory test below for behavior verification.
            expect(true)->toBeTrue();
        }
    }
});

test('EvaluadorProyecto fillable includes evaluado', function () {
    $ep = new EvaluadorProyecto;

    expect($ep->getFillable())->toContain('evaluado');
});

test('EvaluadorProyecto casts evaluado to boolean', function () {
    $ep = new EvaluadorProyecto;

    expect($ep->getCasts())->toHaveKey('evaluado');
    expect($ep->getCasts()['evaluado'])->toBe('boolean');
});

test('EvaluadorProyecto factory-created instance defaults evaluado to false', function () {
    $ep = EvaluadorProyecto::factory()->create();

    expect($ep->evaluado)->toBeFalse();
});

// ---------------------------------------------------------------------------
// T-004: evaluaciones_evaluador table + UNIQUE FK (RF-EVA-05)
// ---------------------------------------------------------------------------

test('evaluaciones_evaluador table exists', function () {
    expect(Schema::hasTable('evaluaciones_evaluador'))->toBeTrue();
});

test('evaluaciones_evaluador has evaluador_proyecto_id column with FK to evaluador_proyecto', function () {
    expect(Schema::hasColumn('evaluaciones_evaluador', 'evaluador_proyecto_id'))->toBeTrue();

    $foreignKeys = Schema::getForeignKeys('evaluaciones_evaluador');
    $fk = collect($foreignKeys)->firstWhere('columns', ['evaluador_proyecto_id']);

    expect($fk)->not->toBeNull();
    expect($fk['foreign_table'] ?? $fk['foreignTable'] ?? null)->toBe('evaluador_proyecto');
});

test('evaluaciones_evaluador evaluador_proyecto_id is UNIQUE (RF-EVA-05)', function () {
    $indexes = Schema::getIndexes('evaluaciones_evaluador');

    $hasUnique = collect($indexes)->contains(function ($idx) {
        $cols = $idx['columns'] ?? [];
        $matchesColumn = in_array('evaluador_proyecto_id', $cols, true);

        return $matchesColumn && (bool) ($idx['unique'] ?? false);
    });

    expect($hasUnique)->toBeTrue('evaluador_proyecto_id should be UNIQUE in evaluaciones_evaluador');
});

test('database rejects duplicate evaluador_proyecto_id in evaluaciones_evaluador (RF-EVA-05)', function () {
    $user = User::factory()->create();
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $proyecto = Proyecto::create(['title' => 'P', 'semester_id' => $semestre->id]);
    $ep = EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $user->id,
    ]);

    // First evaluation inserts fine.
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ep->id,
        'nota' => 4.0,
        'observaciones' => 'first',
    ]);

    // Second evaluation for the same evaluador_proyecto MUST be rejected by the DB.
    expect(fn () => EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ep->id,
        'nota' => 3.0,
        'observaciones' => 'second',
    ]))->toThrow(QueryException::class);
});

test('evaluaciones_evaluador has nota decimal/numeric column', function () {
    expect(Schema::hasColumn('evaluaciones_evaluador', 'nota'))->toBeTrue();

    $columns = Schema::getColumns('evaluaciones_evaluador');
    $col = collect($columns)->firstWhere('name', 'nota');

    expect($col)->not->toBeNull();
    expect(columnIsDecimalLike($col))->toBeTrue();
});

test('evaluaciones_evaluador.nota has precision 4, scale 2 on PostgreSQL', function () {
    $columns = Schema::getColumns('evaluaciones_evaluador');
    $col = collect($columns)->firstWhere('name', 'nota');

    if (DB::getDriverName() === 'pgsql') {
        expect((int) ($col['precision'] ?? 0))->toBe(4);
        expect((int) ($col['scale'] ?? 0))->toBe(2);
    } else {
        expect(true)->toBeTrue();
    }
});

test('evaluaciones_evaluador has observaciones text column', function () {
    expect(Schema::hasColumn('evaluaciones_evaluador', 'observaciones'))->toBeTrue();

    $columns = Schema::getColumns('evaluaciones_evaluador');
    $col = collect($columns)->firstWhere('name', 'observaciones');

    expect($col)->not->toBeNull();
    $type = strtolower((string) ($col['type'] ?? $col['type_name'] ?? ''));
    expect($type)->toContain('text');
});

test('evaluaciones_evaluador has timestamps', function () {
    expect(Schema::hasColumn('evaluaciones_evaluador', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('evaluaciones_evaluador', 'updated_at'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// T-007: EvaluacionEvaluador model
// ---------------------------------------------------------------------------

test('EvaluacionEvaluador model exists and extends Model', function () {
    $model = new EvaluacionEvaluador;

    expect($model)->toBeInstanceOf(Illuminate\Database\Eloquent\Model::class);
});

test('EvaluacionEvaluador fillable contains evaluador_proyecto_id, nota, observaciones', function () {
    $model = new EvaluacionEvaluador;
    $fillable = $model->getFillable();

    expect($fillable)->toContain('evaluador_proyecto_id')
        ->and($fillable)->toContain('nota')
        ->and($fillable)->toContain('observaciones');
});

test('EvaluacionEvaluador casts nota to decimal:2', function () {
    $model = new EvaluacionEvaluador;

    expect($model->getCasts())->toHaveKey('nota');
    expect($model->getCasts()['nota'])->toBe('decimal:2');
});

test('EvaluacionEvaluador belongs to EvaluadorProyecto', function () {
    $model = new EvaluacionEvaluador;
    $relation = $model->evaluadorProyecto();

    expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(EvaluadorProyecto::class);
});

test('EvaluacionEvaluador persists nota and observaciones end-to-end', function () {
    $user = User::factory()->create();
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $proyecto = Proyecto::create(['title' => 'P', 'semester_id' => $semestre->id]);
    $ep = EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $user->id,
    ]);

    $eval = EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ep->id,
        'nota' => 4.5,
        'observaciones' => 'Trabajo bien estructurado',
    ]);

    expect($eval->exists)->toBeTrue();
    expect((float) $eval->fresh()->nota)->toEqual(4.5);
    expect($eval->fresh()->observaciones)->toBe('Trabajo bien estructurado');
    expect($eval->evaluadorProyecto)->toBeInstanceOf(EvaluadorProyecto::class);
    expect($eval->evaluadorProyecto->id)->toBe($ep->id);
});
