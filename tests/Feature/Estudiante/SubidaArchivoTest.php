<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    // Create entrega with archivos_requeridos via the controller-like setup
    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega Test',
        'description' => 'Desc',
        'due_date' => '2026-09-15',
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento', 'nombre' => 'Documento Principal', 'versionamiento' => true],
            ['slug' => 'anexos', 'nombre' => 'Anexos', 'versionamiento' => false],
        ],
    ]);

    // Link entrega to the student's project via pivot
    $this->entrega->proyectos()->attach($this->proyecto->id);
});

it('uploads with versioning creates new version without removing old ones', function () {
    $file1 = UploadedFile::fake()->create('doc_v1.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file1])
        ->assertStatus(201);

    $file2 = UploadedFile::fake()->create('doc_v2.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file2])
        ->assertStatus(201);

    // Both versions should exist (versioning enabled)
    $pivot = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyecto->id)
        ->first();

    $versiones = VersionDocumento::where('entrega_proyecto_id', $pivot->id)
        ->where('archivo_requerido_id', 'documento')
        ->where('descontinuado', false)
        ->get();

    expect($versiones)->toHaveCount(2);
    expect($versiones[0]->version_number)->toBe(1);
    expect($versiones[1]->version_number)->toBe(2);
});

it('uploads without versioning replaces existing file', function () {
    $file1 = UploadedFile::fake()->create('anexos_v1.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/anexos", ['file' => $file1])
        ->assertStatus(201);

    $file2 = UploadedFile::fake()->create('anexos_v2.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/anexos", ['file' => $file2])
        ->assertStatus(201);

    $pivot = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyecto->id)
        ->first();

    // Without versioning, old versions are marked descontinuado
    $activas = VersionDocumento::where('entrega_proyecto_id', $pivot->id)
        ->where('archivo_requerido_id', 'anexos')
        ->where('descontinuado', false)
        ->get();

    expect($activas)->toHaveCount(1);
    expect($activas[0]->version_number)->toBe(2);

    // Old version should be marked descontinuado
    $v1 = VersionDocumento::where('entrega_proyecto_id', $pivot->id)
        ->where('archivo_requerido_id', 'anexos')
        ->where('version_number', 1)
        ->first();
    expect($v1->descontinuado)->toBeTrue();
});

it('returns 404 for non-existent slug', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/inexistente", ['file' => $file])
        ->assertStatus(404);
});

it('enforces max 4 versions per archivo_requerido', function () {
    $pivot = EntregaProyecto::firstOrCreate(
        ['entrega_id' => $this->entrega->id, 'proyecto_id' => $this->proyecto->id],
        ['estado' => 'pendiente']
    );

    // Upload 4 versions
    for ($i = 0; $i < 4; $i++) {
        $file = UploadedFile::fake()->create("doc_v{$i}.pdf", 100);
        $this->actingAs($this->estudiante)
            ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file])
            ->assertStatus(201);
    }

    // 5th upload should fail
    $file5 = UploadedFile::fake()->create('doc_v5.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file5])
        ->assertStatus(422);
});
