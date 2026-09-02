<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->director = User::factory()->director()->create();
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
});

/**
 * Seed an active entrega (non-terminal status, future due date) with its
 * per-project delivery (EntregaProyecto) and one linked version so the
 * review endpoint's version_id contract is satisfied (D3-rev).
 *
 * @return array{entrega: Entrega, version: VersionDocumento, pivot: EntregaProyecto}
 */
function crearEntregaRevisable(Proyecto $proyecto, array $overrides = []): array
{
    $entrega = Entrega::create(array_merge([
        'semester_id' => $proyecto->semester_id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega para revisar',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
    ], $overrides));

    $pivot = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $proyecto->id,
        'estado' => 'pendiente',
    ]);

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'test.pdf',
        'uploaded_at' => now(),
    ]);

    return ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot];
}

/**
 * Default review payload for the director PUT /revisar endpoint.
 */
function payloadRevisar(array $overrides = []): array
{
    return array_merge([
        'status' => 'aprobada',
        'consolidated_grade' => 4.5,
        'director_notes' => 'Buen trabajo',
        'version_id' => null,
    ], $overrides);
}

// -- RF-NOT-02 / RF-NOT-03: persist director_grade at approval ----------------

it('aprueba con director_grade y lo persiste en la entrega del proyecto (RF-NOT-02 / D3-rev)', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'director_grade' => 4.5,
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    // D3-rev: the note belongs to the per-project delivery (EntregaProyecto),
    // resolved from the reviewed version.
    $this->assertDatabaseHas('entrega_proyecto', ['id' => $pivot->id, 'director_grade' => 4.5]);
    // The general delivery template (entregas) never stores the note.
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'director_grade' => null]);
});

it('persiste las observaciones del director en la entrega del proyecto (D3-rev)', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaRevisable($this->proyecto);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'director_grade' => 4.0,
            'version_id' => $version->id,
        ]))
        ->assertOk();

    expect($pivot->fresh()->observaciones_director)->toBe('Buen trabajo');
});

it('permite re-revisar observaciones mientras la entrega sigue activa (RF-NOT-03)', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto, ['status' => 'revisada']);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'status' => 'revisada',
            'director_notes' => 'Corregir sección 2',
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    expect($entrega->fresh()->status->value)->toBe('revisada');
});

// -- RF-NOT-02: the grade is only captured at approval time --------------------

it('revisa sin aprobar sin exigir director_grade (RF-NOT-02)', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'status' => 'revisada',
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    $this->assertDatabaseHas('entrega_proyecto', ['id' => $pivot->id, 'director_grade' => null]);
});

it('no persiste director_grade cuando la entrega no se aprueba (RF-NOT-02)', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'status' => 'revisada',
            'director_grade' => 4.0,
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    $this->assertDatabaseHas('entrega_proyecto', ['id' => $pivot->id, 'director_grade' => null]);
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'director_grade' => null]);
});

// -- D3-rev: per-project note independence -------------------------------------

it('no comparte la nota del director entre proyectos de la misma entrega general (D3-rev)', function () {
    $proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    ['entrega' => $eA, 'version' => $vA, 'pivot' => $pivotA] = crearEntregaRevisable($this->proyecto, ['title' => 'Entrega general 1']);
    ['entrega' => $eB, 'version' => $vB, 'pivot' => $pivotB] = crearEntregaRevisable($proyectoB, ['title' => 'Entrega general 2']);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$eA->id}/revisar", payloadRevisar([
            'director_grade' => 4.5,
            'version_id' => $vA->id,
        ]))
        ->assertOk();

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$eB->id}/revisar", payloadRevisar([
            'director_grade' => 3.5,
            'version_id' => $vB->id,
        ]))
        ->assertOk();

    expect((float) $pivotA->fresh()->director_grade)->toBe(4.5);
    expect((float) $pivotB->fresh()->director_grade)->toBe(3.5);
    expect($pivotA->id)->not->toBe($pivotB->id);
    $this->assertDatabaseHas('entregas', ['id' => $eA->id, 'director_grade' => null]);
    $this->assertDatabaseHas('entregas', ['id' => $eB->id, 'director_grade' => null]);
});

// -- RF-NOT-01 / D7: range and precision ---------------------------------------

it('rechaza director_grade fuera del rango 0-5 (RF-NOT-01)', function (float $grade) {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'director_grade' => $grade,
            'version_id' => $version->id,
        ]));

    $response->assertStatus(422);
    expect($response->json('error.message'))->toBe('La nota del director debe estar entre 0 y 5');
})->with([5.01, 6.0, -0.5]);

it('rechaza director_grade con más de 2 decimales (D7)', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'director_grade' => 4.567,
            'version_id' => $version->id,
        ]));

    $response->assertStatus(422);
    expect($response->json('error.message'))->toBe('La nota del director debe tener máximo 2 decimales');
});

// -- RF-NOT-03: closed entregas reject any edit --------------------------------

it('rechaza revisar cuando la entrega ya está cerrada por status terminal (RF-NOT-03)', function (string $status) {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto, ['status' => $status]);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar(['version_id' => $version->id]));

    $response->assertStatus(422);
    expect($response->json('error.message'))
        ->toBe('La entrega está cerrada; la nota y las observaciones no pueden modificarse');
})->with(['aprobada', 'rechazada']);

it('el director puede revisar aunque la due_date haya vencido (solo status terminal bloquea)', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto, [
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar(['version_id' => $version->id]));

    // Director CAN review after due_date — only terminal status (aprobada/rechazada) blocks
    $response->assertOk();
    expect($entrega->fresh()->status->value)->toBe('aprobada');
});
