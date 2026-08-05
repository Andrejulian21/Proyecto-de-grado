<?php

declare(strict_types=1);

use App\Enums\FaseProyecto;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
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

// -- Listar entregas --------------------------------------------------------

it('coordinador puede listar entregas', function () {
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'proyecto_id', 'phase', 'title', 'status']]]);
});

it('listar entregas filtra por proyecto_id', function () {
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'A', 'due_date' => '2026-03-01']);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas?proyecto_id='.$this->proyecto->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('listar entregas filtra por fase', function () {
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'A', 'due_date' => '2026-03-01']);
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'desarrollo', 'title' => 'B', 'due_date' => '2026-04-01']);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas?fase=anteproyecto');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('estudiante ve solo sus entregas', function () {
    $otroProyecto = Proyecto::create(['title' => 'Otro', 'semester_id' => $this->semestre->id]);
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Mi entrega', 'due_date' => '2026-03-01']);
    Entrega::create(['proyecto_id' => $otroProyecto->id, 'phase' => 'anteproyecto', 'title' => 'No visible', 'due_date' => '2026-03-01']);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/admin/entregas');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data')[0]['title'])->toBe('Mi entrega');
});

// -- Crear entrega ----------------------------------------------------------

it('coordinador puede crear entrega', function () {
    $payload = [
        'grupo_id' => $this->semestre->id,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega Anteproyecto',
        'descripcion' => 'Descripción detallada del anteproyecto',
        'fecha_limite' => '2026-09-15',
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
            ['id' => 'anexos', 'nombre' => 'Anexos', 'versionamiento' => false],
        ],
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', $payload);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'semester_id', 'phase', 'title', 'status']]);
    expect(Entrega::count())->toBe(1);
    expect($response->json('data.title'))->toBe('Entrega Anteproyecto');
    expect($response->json('data.semester_id'))->toBe($this->semestre->id);
    expect($response->json('data.archivos_requeridos'))->toHaveCount(2);
});

it('crear entrega valida campos requeridos', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', []);

    $response->assertStatus(422);
});

it('estudiante NO puede crear entrega (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'descripcion' => 'Hack',
            'fecha_limite' => '2026-03-01',
        ]);

    $response->assertStatus(403);
});

// -- Subir versiones --------------------------------------------------------
//
// REMOVED 2026-08-04: these 6 tests referenced POST /api/entregas/{id}/versiones
// which was eliminated in PR 2 (replaced by the per-slug upload endpoint
// POST /api/entregas/{entrega}/archivos/{slug}). The new path is exercised by
// SubidaArchivoTest and EstadoCompletitudTest, which already cover the upload
// flow, max-versions, file-type, file-size and RBAC scenarios.
//
// The 'revisar' tests below still depend on a VersionDocumento row existing
// to satisfy the controller's version_id contract — they create the version
// directly via the model instead of through the (now-removed) upload route.

// -- Historial de versiones -------------------------------------------------

it('estudiante puede ver historial de versiones', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);
    VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'path/v1.pdf',
        'original_name' => 'v1.pdf',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/entregas/{$entrega->id}/versiones");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

// -- Revisar entrega (director) ---------------------------------------------

it('director puede aprobar entrega con nota y feedback', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
    ]);

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'test.pdf',
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Buen trabajo',
            'version_id' => $version->id,
        ]);

    $response->assertOk();
    $entrega->refresh();
    expect($entrega->status->value)->toBe('aprobada');
    expect((float) $entrega->consolidated_grade)->toEqual(4.5);
    expect($entrega->evaluation_complete)->toBeTrue();
});

it('estudiante NO puede revisar entrega (403)', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
    ]);

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'test.pdf',
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'version_id' => $version->id,
        ]);

    $response->assertStatus(403);
});

// -- Banco de documentos aprobados ------------------------------------------

it('entregas finales endpoint devuelve documentos aprobados', function () {
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Aprobada', 'due_date' => '2026-03-01', 'status' => 'aprobada']);
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'No aprobada', 'due_date' => '2026-03-01', 'status' => 'pendiente']);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data')[0]['title'])->toBe('Aprobada');
});

// -- Avance automático de fase ----------------------------------------------

it('al aprobar ultima entrega de fase avanza proyecto a siguiente fase', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Única entrega anteproyecto',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
    ]);

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'test.pdf',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Aprobado',
            'version_id' => $version->id,
        ]);

    $this->proyecto->refresh();
    expect($this->proyecto->current_phase)->toBe(FaseProyecto::PresentacionAnteproyecto);
});
