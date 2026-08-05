<?php

declare(strict_types=1);

use App\Models\Entrega;
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
 * Seed an active entrega (non-terminal status, future due date) with one
 * version so the review endpoint's version_id contract is satisfied.
 *
 * @return array{entrega: Entrega, version: VersionDocumento}
 */
function crearEntregaRevisable(Proyecto $proyecto, array $overrides = []): array
{
    $entrega = Entrega::create(array_merge([
        'proyecto_id' => $proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega para revisar',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
    ], $overrides));

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'test.pdf',
        'uploaded_at' => now(),
    ]);

    return ['entrega' => $entrega, 'version' => $version];
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

it('aprueba con director_grade y lo persiste en la entrega (RF-NOT-02)', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'director_grade' => 4.5,
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    expect($response->json('data.director_grade'))->toBe('4.50');
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'director_grade' => 4.5]);
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
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'status' => 'revisada',
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'director_grade' => null]);
});

it('no persiste director_grade cuando la entrega no se aprueba (RF-NOT-02)', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar([
            'status' => 'revisada',
            'director_grade' => 4.0,
            'version_id' => $version->id,
        ]));

    $response->assertOk();
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'director_grade' => null]);
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

it('rechaza revisar cuando la due_date ya venció (RF-NOT-03)', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaRevisable($this->proyecto, [
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", payloadRevisar(['version_id' => $version->id]));

    $response->assertStatus(422);
    expect($response->json('error.message'))
        ->toBe('La entrega está cerrada; la nota y las observaciones no pueden modificarse');
});
