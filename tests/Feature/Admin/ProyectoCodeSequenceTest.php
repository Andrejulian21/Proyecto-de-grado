<?php

declare(strict_types=1);

use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::create([
        'name' => '2026-2',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
    ]);
    // `Proyecto::creating()` derives the code from the semester name, so the
    // test computes the same expectation instead of hardcoding the suffix.
    $this->semestreCode = str_replace('-', '', $this->semestre->name);
});

/**
 * Create a project through the coordinator endpoint — the exact path
 * production uses.
 */
function crearProyectoComoCoordinador(object $test, string $title): TestResponse
{
    return $test->actingAs($test->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => $title,
            'semester_id' => $test->semestre->id,
        ]);
}

it('genera una secuencia correlativa sin huecos cuando no hay borrados', function () {
    foreach (['A', 'B', 'C'] as $index => $title) {
        crearProyectoComoCoordinador($this, $title)
            ->assertCreated()
            ->assertJsonPath('data.code', 'PG-'.$this->semestreCode.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
    }
});

/**
 * Known limitation, pinned on purpose.
 *
 * A MAX-based sequence reads the surviving rows, so it cannot know a code was
 * ever issued to a row that no longer exists. Deleting the HIGHEST project of
 * a semester therefore lets that number be issued again. That is harmless for
 * correctness — nothing collides and no request 500s, which is what the outage
 * was — but it is not the same as "codes are never reused". Closing that gap
 * needs a persisted per-semester counter (a column, a table or a dedicated
 * sequence), which is a migration and a product decision, out of scope here.
 *
 * What this test does lock in: the deletion never surfaces as a 500 and the
 * surviving codes stay unique.
 */
it('crea sin 500 tras hard-delete del último proyecto', function () {
    crearProyectoComoCoordinador($this, 'A')->assertCreated();
    crearProyectoComoCoordinador($this, 'B')->assertCreated();
    crearProyectoComoCoordinador($this, 'C')->assertCreated();

    expect(Proyecto::where('semester_id', $this->semestre->id)->pluck('code')->all())
        ->toBe([
            'PG-'.$this->semestreCode.'001',
            'PG-'.$this->semestreCode.'002',
            'PG-'.$this->semestreCode.'003',
        ]);

    // Production delete path: `Proyecto` has no SoftDeletes, so
    // `ProyectoController::destroy()` calls `forceDelete()` — the row leaves the
    // table for good and the sequence keeps a permanent hole at 003.
    Proyecto::where('code', 'PG-'.$this->semestreCode.'003')->firstOrFail()->forceDelete();

    // Proof the gap exists: only two rows remain, so a COUNT-based sequence
    // would hand out 003 again.
    expect(Proyecto::where('semester_id', $this->semestre->id)->count())->toBe(2);

    // The deletion itself must never surface as a 500 on the next creation,
    // and no code may end up written twice.
    crearProyectoComoCoordinador($this, 'D')->assertCreated();

    expect(Proyecto::where('semester_id', $this->semestre->id)->count())->toBe(3);
    expect(Proyecto::where('semester_id', $this->semestre->id)->pluck('code')->unique())
        ->toHaveCount(3);
    expect(Proyecto::where('semester_id', $this->semestre->id)->pluck('code')->all())
        ->toBe([
            'PG-'.$this->semestreCode.'001',
            'PG-'.$this->semestreCode.'002',
            'PG-'.$this->semestreCode.'003',
        ]);
});

it('crea sin 500 cuando el hueco está en medio y el siguiente código ya existe', function () {
    // Exact production state: 001..004 existed, someone hard-deleted 003 and
    // 004 survived. A COUNT()-based sequence returns 3 + 1 = 4, regenerating
    // an occupied code and blowing up the `code` UNIQUE index with a 500.
    foreach (['A', 'B', 'C', 'D'] as $title) {
        crearProyectoComoCoordinador($this, $title)->assertCreated();
    }

    Proyecto::where('code', 'PG-'.$this->semestreCode.'003')->firstOrFail()->forceDelete();

    expect(Proyecto::where('semester_id', $this->semestre->id)->count())->toBe(3);

    crearProyectoComoCoordinador($this, 'E')
        ->assertCreated()
        ->assertJsonPath('data.code', 'PG-'.$this->semestreCode.'005');

    // The regenerated 004 was not written twice.
    expect(Proyecto::where('code', 'PG-'.$this->semestreCode.'004')->count())->toBe(1);
    expect(Proyecto::where('semester_id', $this->semestre->id)->pluck('code')->unique())
        ->toHaveCount(Proyecto::where('semester_id', $this->semestre->id)->count());
});

it('la secuencia sobrevive varios borrados hard consecutivos', function () {
    foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
        crearProyectoComoCoordinador($this, $title)->assertCreated();
    }

    Proyecto::whereIn('code', [
        'PG-'.$this->semestreCode.'002',
        'PG-'.$this->semestreCode.'003',
        'PG-'.$this->semestreCode.'004',
    ])->forceDelete();

    expect(Proyecto::where('semester_id', $this->semestre->id)->count())->toBe(2);

    crearProyectoComoCoordinador($this, 'F')
        ->assertCreated()
        ->assertJsonPath('data.code', 'PG-'.$this->semestreCode.'006');

    expect(Proyecto::where('semester_id', $this->semestre->id)->pluck('code')->unique())
        ->toHaveCount(Proyecto::where('semester_id', $this->semestre->id)->count());
});

it('la secuencia es independiente por semestre', function () {
    $otroSemestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    crearProyectoComoCoordinador($this, 'A')->assertCreated();

    $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'B',
            'semester_id' => $otroSemestre->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PG-'.str_replace('-', '', $otroSemestre->name).'001');
});
