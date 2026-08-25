<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);
});

// -- Solicitar habilitación --------------------------------------------------

it('estudiante puede solicitar habilitación de entrega', function () {
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        // Future-dated so the controller's due_date check does not reject
        // the request with 422 "fecha límite ya pasó".
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'creacion',
    ]);
    $entrega->proyectos()->attach($this->proyecto->id);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/solicitar");

    $response->assertOk();
    $entrega->refresh();
    expect($entrega->status->value)->toBe('solicitada');
});

it('solicitar registra en auditoría', function () {
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'creacion',
    ]);
    $entrega->proyectos()->attach($this->proyecto->id);

    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/solicitar");

    expect(AuditLog::where('action', 'entrega.solicitar')->count())->toBe(1);
});

it('solicitar falla si entrega no está en creación (422)', function () {
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'pendiente',
    ]);
    $entrega->proyectos()->attach($this->proyecto->id);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/solicitar");

    $response->assertStatus(422);
});

// -- Director habilita --------------------------------------------------------

it('director puede habilitar entrega solicitada', function () {
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'solicitada',
    ]);
    $entrega->proyectos()->attach($this->proyecto->id);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/habilitar");

    $response->assertOk();
    $entrega->refresh();
    expect($entrega->status->value)->toBe('pendiente');
});

it('no coordinador no puede habilitar entrega (403)', function () {
    $otroDirector = User::factory()->director()->create();
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'solicitada',
    ]);
    $entrega->proyectos()->attach($this->proyecto->id);

    $response = $this->actingAs($otroDirector)
        ->putJson("/api/admin/entregas/{$entrega->id}/habilitar");

    $response->assertStatus(403);
});

// REMOVED 2026-08-04: the 2 tests below referenced
// POST /api/entregas/{id}/versiones which was eliminated in PR 2
// (replaced by per-slug POST /api/entregas/{entrega}/archivos/{slug}).
// Upload flow + RBAC + max-versions are covered by SubidaArchivoTest
// and EstadoCompletitudTest.
