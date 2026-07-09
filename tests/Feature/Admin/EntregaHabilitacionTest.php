<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'creacion',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/solicitar");

    $response->assertOk();
    $entrega->refresh();
    expect($entrega->status->value)->toBe('solicitada');
});

it('solicitar registra en auditoría', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'creacion',
    ]);

    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/solicitar");

    expect(AuditLog::where('action', 'entrega.solicitar')->count())->toBe(1);
});

it('solicitar falla si entrega no está en creación (422)', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'pendiente',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/solicitar");

    $response->assertStatus(422);
});

// -- Director habilita --------------------------------------------------------

it('director puede habilitar entrega solicitada', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'solicitada',
    ]);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/habilitar");

    $response->assertOk();
    $entrega->refresh();
    expect($entrega->status->value)->toBe('pendiente');
});

it('no coordinador no puede habilitar entrega (403)', function () {
    $otroDirector = User::factory()->director()->create();
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'solicitada',
    ]);

    $response = $this->actingAs($otroDirector)
        ->putJson("/api/admin/entregas/{$entrega->id}/habilitar");

    $response->assertStatus(403);
});

// -- Subir versiones con habilitación -----------------------------------------

it('estudiante NO puede subir versión sin habilitación (422)', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'solicitada', // no habilitada aún
    ]);

    $file = UploadedFile::fake()->create('documento.pdf', 100);
    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/versiones", ['file' => $file]);

    $response->assertStatus(422);
});

it('estudiante puede subir versión después de habilitación', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
        'status' => 'pendiente', // habilitada por director
    ]);

    $file = UploadedFile::fake()->create('documento.pdf', 100);
    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/versiones", ['file' => $file]);

    $response->assertCreated();
});
