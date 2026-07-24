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
        ->getJson('/api/admin/entregas?proyecto_id=' . $this->proyecto->id);

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
        'fecha_limite' => '2026-03-15',
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', $payload);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'semester_id', 'phase', 'title', 'status']]);
    expect(Entrega::count())->toBe(1);
    expect($response->json('data.title'))->toBe('Entrega Anteproyecto');
    expect($response->json('data.semester_id'))->toBe($this->semestre->id);
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

it('estudiante puede subir version a entrega', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);

    $file = UploadedFile::fake()->create('documento.pdf', 100);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/versiones", [
            'file' => $file,
        ]);

    $response->assertCreated();
    expect(VersionDocumento::count())->toBe(1);
});

it('version numero se auto-incrementa por entrega', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);

    $file1 = UploadedFile::fake()->create('v1.pdf', 100);
    $this->actingAs($this->estudiante)->postJson("/api/entregas/{$entrega->id}/versiones", ['file' => $file1]);

    $file2 = UploadedFile::fake()->create('v2.pdf', 100);
    $this->actingAs($this->estudiante)->postJson("/api/entregas/{$entrega->id}/versiones", ['file' => $file2]);

    $versiones = VersionDocumento::where('entrega_id', $entrega->id)->orderBy('version_number')->get();
    expect($versiones[0]->version_number)->toBe(1);
    expect($versiones[1]->version_number)->toBe(2);
});

it('maximo 4 versiones por entrega (422)', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $file = UploadedFile::fake()->create("v{$i}.pdf", 100);
        $this->actingAs($this->estudiante)->postJson("/api/entregas/{$entrega->id}/versiones", ['file' => $file]);
    }

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/versiones", [
            'file' => UploadedFile::fake()->create('v5.pdf', 100),
        ]);

    $response->assertStatus(422);
});

it('subir version valida tipo de archivo PDF/DOCX', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);

    $file = UploadedFile::fake()->create('malo.exe', 100);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/versiones", [
            'file' => $file,
        ]);

    $response->assertStatus(422);
});

it('subir version valida tamaño máximo 50MB', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);

    $file = UploadedFile::fake()->create('grande.pdf', 60000);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$entrega->id}/versiones", [
            'file' => $file,
        ]);

    $response->assertStatus(422);
});

it('coordinador NO puede subir version (403)', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => '2026-03-01',
    ]);

    $file = UploadedFile::fake()->create('doc.pdf', 100);
    $response = $this->actingAs($this->coordinador)
        ->postJson("/api/entregas/{$entrega->id}/versiones", ['file' => $file]);

    $response->assertStatus(403);
});

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
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);

    $response = $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Buen trabajo',
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
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
        ]);

    $response->assertStatus(403);
});

it('director guarda director_notes por version_id sin sobrescribir otras versiones', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega multi-versión',
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);

    $v1 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'path/v1.pdf',
        'original_name' => 'v1.pdf',
    ]);
    $v2 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 2,
        'file_path' => 'path/v2.pdf',
        'original_name' => 'v2.pdf',
    ]);
    $v3 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 3,
        'file_path' => 'path/v3.pdf',
        'original_name' => 'v3.pdf',
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Corregir introducción.',
            'version_id' => $v1->id,
        ])
        ->assertOk();

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Corregir metodología.',
            'version_id' => $v2->id,
        ])
        ->assertOk();

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'director_notes' => 'Aprobada.',
            'version_id' => $v3->id,
        ])
        ->assertOk();

    expect($v1->fresh()->director_notes)->toBe('Corregir introducción.');
    expect($v2->fresh()->director_notes)->toBe('Corregir metodología.');
    expect($v3->fresh()->director_notes)->toBe('Aprobada.');
});

it('revisar con version_id de otra entrega retorna 422', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega A',
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);
    VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'path/a.pdf',
        'original_name' => 'a.pdf',
    ]);

    $otraEntrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega B',
        'due_date' => '2026-03-15',
        'status' => 'enviada',
    ]);
    $versionAjena = VersionDocumento::create([
        'entrega_id' => $otraEntrega->id,
        'version_number' => 1,
        'file_path' => 'path/b.pdf',
        'original_name' => 'b.pdf',
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'No debe guardarse',
            'version_id' => $versionAjena->id,
        ])
        ->assertStatus(422);
});

it('revisar sin version_id guarda notas en la ultima version', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega fallback',
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);

    $v1 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'path/v1.pdf',
        'original_name' => 'v1.pdf',
        'director_notes' => 'Nota histórica v1',
    ]);
    $v2 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 2,
        'file_path' => 'path/v2.pdf',
        'original_name' => 'v2.pdf',
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Nota en última',
        ])
        ->assertOk();

    expect($v1->fresh()->director_notes)->toBe('Nota histórica v1');
    expect($v2->fresh()->director_notes)->toBe('Nota en última');
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
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Aprobado',
        ]);

    $this->proyecto->refresh();
    expect($this->proyecto->current_phase)->toBe(FaseProyecto::PresentacionAnteproyecto);
});
