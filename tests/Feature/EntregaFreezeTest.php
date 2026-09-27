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
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * RF-FREEZE-01 — pivot-scoped upload/delete freeze on graded pivot.
 *
 * P1 (graded pivot) blocks upload/delete; P2 (ungraded pivot on the same
 * shared template) still uploads. The shared template itself never freezes.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->semestre = Semestre::factory()->create(['is_active' => true]);

    $this->estudianteA = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->proyectoA = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
    $this->proyectoA->estudiantes()->attach($this->estudianteA);

    $this->estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->proyectoB = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
    $this->proyectoB->estudiantes()->attach($this->estudianteB);

    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega con freeze por pivote',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento', 'nombre' => 'Documento Principal', 'versionamiento' => true],
        ],
    ]);
    $this->entrega->proyectos()->attach([$this->proyectoA->id, $this->proyectoB->id]);

    // P1 graded (frozen), P2 ungraded (open).
    $this->pivotA = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyectoA->id)
        ->firstOrFail();
    $this->pivotA->update(['director_grade' => 4.5]);

    $this->pivotB = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyectoB->id)
        ->firstOrFail();
});

it('blocks student upload on the graded pivot P1 with 403 PIVOT_FROZEN', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100);

    $response = $this->actingAs($this->estudianteA)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file]);

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'PIVOT_FROZEN');
});

it('allows upload on the ungraded pivot P2 of the same template', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100);

    $this->actingAs($this->estudianteB)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file])
        ->assertStatus(201);
});

it('blocks version delete on the graded pivot P1', function () {
    $version = VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'entrega_proyecto_id' => $this->pivotA->id,
        'archivo_requerido_id' => 'documento',
        'version_number' => 1,
        'file_path' => 'entregas/freeze/v1.pdf',
        'file_size' => 1024,
        'original_name' => 'v1.pdf',
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($this->estudianteA)
        ->deleteJson("/api/entregas/{$this->entrega->id}/versiones/{$version->id}");

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'PIVOT_FROZEN');
    expect(VersionDocumento::find($version->id))->not->toBeNull();
});

it('reopens upload when the pivot is rechazada with null grade', function () {
    $this->pivotA->update(['director_grade' => null, 'estado' => 'rechazada']);
    $file = UploadedFile::fake()->create('correccion.pdf', 100);

    $this->actingAs($this->estudianteA)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file])
        ->assertStatus(201);
});

it('habilitar clears the pivot grade and unfreezes upload', function () {
    $this->entrega->update(['status' => 'solicitada']);
    $director = User::factory()->director()->create();
    $this->proyectoA->update(['director_id' => $director->id]);

    $this->actingAs($director)
        ->putJson("/api/admin/entregas/{$this->entrega->id}/habilitar")
        ->assertOk();

    expect($this->pivotA->fresh()->director_grade)->toBeNull();

    $file = UploadedFile::fake()->create('doc.pdf', 100);
    $this->actingAs($this->estudianteA)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file])
        ->assertStatus(201);
});

it('habilitar unfreezes an approved graded delivery', function () {
    $this->entrega->update(['status' => 'aprobada']);
    $director = User::factory()->director()->create();
    $this->proyectoA->update(['director_id' => $director->id]);

    $this->actingAs($director)
        ->putJson("/api/admin/entregas/{$this->entrega->id}/habilitar")
        ->assertOk();

    expect($this->pivotA->fresh()->director_grade)->toBeNull();

    $file = UploadedFile::fake()->create('doc.pdf', 100);
    $this->actingAs($this->estudianteA)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file])
        ->assertStatus(201);
});
