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
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * DELETE /api/entregas/{entregaId}/versiones/{versionId}
 *
 * Issue #40: este endpoint no tenía cobertura de camino feliz ni de error
 * (la matriz #38 solo cubre el RBAC por rol). Aquí se cubren las tres ramas:
 * borrado físico (archivo + fila), rechazo por observaciones del director,
 * y rechazo por rol no estudiante.
 */

beforeEach(function () {
    Storage::fake('public');

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    // Production shape (StoreEntregaAction): semester_id + pivot.
    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega con versión',
        'due_date' => '2026-09-15',
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ]);
    $this->entrega->proyectos()->attach($this->proyecto->id);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function crearVersionEliminable(Entrega $entrega, array $overrides = []): VersionDocumento
{
    $pivot = EntregaProyecto::where('entrega_id', $entrega->id)->firstOrFail();

    return VersionDocumento::create(array_merge([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'version_number' => 1,
        'file_path' => 'entregas/eliminar/v1.pdf',
        'file_size' => 1024,
        'original_name' => 'v1.pdf',
        'uploaded_at' => now(),
    ], $overrides));
}

it('el estudiante elimina una versión sin observaciones y borra el archivo', function () {
    Storage::disk('public')->put('entregas/eliminar/v1.pdf', 'contenido');
    $version = crearVersionEliminable($this->entrega);

    $response = $this->actingAs($this->estudiante)
        ->deleteJson("/api/entregas/{$this->entrega->id}/versiones/{$version->id}");

    $response->assertOk()
        ->assertJson(['message' => 'Versión eliminada correctamente.']);

    expect(VersionDocumento::find($version->id))->toBeNull();
    expect(Storage::disk('public')->exists('entregas/eliminar/v1.pdf'))->toBeFalse();
});

it('no elimina una versión que ya tiene observaciones del director (422)', function () {
    $version = crearVersionEliminable($this->entrega, ['director_notes' => 'Revisar estructura']);

    $response = $this->actingAs($this->estudiante)
        ->deleteJson("/api/entregas/{$this->entrega->id}/versiones/{$version->id}");

    $response->assertStatus(422)
        ->assertJson(['error' => 'No se puede eliminar una versión que ya tiene observaciones del director.']);

    expect(VersionDocumento::find($version->id))->not->toBeNull();
});

it('un director no puede eliminar la versión de un estudiante (403)', function () {
    $director = User::factory()->director()->create();
    $version = crearVersionEliminable($this->entrega);

    $this->actingAs($director)
        ->deleteJson("/api/entregas/{$this->entrega->id}/versiones/{$version->id}")
        ->assertStatus(403);

    expect(VersionDocumento::find($version->id))->not->toBeNull();
});

/**
 * Issue #46 — la entrega es una plantilla compartida por todos los proyectos
 * del semestre. La versión pertenece a UN proyecto concreto (a través del
 * pivote entrega_proyecto). Un estudiante de otro proyecto de la misma
 * entrega no puede eliminarla: 404 (no 403) para no confirmar la existencia
 * de un documento ajeno.
 */
it('un estudiante de otro proyecto no puede eliminar una versión ajena (404)', function () {
    $estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $proyectoB = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
    $proyectoB->estudiantes()->attach($estudianteB);

    // Idempotente: el boot hook de Proyecto ya auto-vincula al semestre, pero
    // el attach explícito hace el escenario determinista (unique entrega+proyecto).
    if (! $this->entrega->proyectos()->where('proyecto_id', $proyectoB->id)->exists()) {
        $this->entrega->proyectos()->attach($proyectoB->id);
    }

    // La versión pertenece a la entrega del proyecto A (pivote A).
    $pivotA = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyecto->id)
        ->firstOrFail();
    Storage::disk('public')->put('entregas/ajena/v1.pdf', 'contenido');
    $version = crearVersionEliminable($this->entrega, [
        'entrega_proyecto_id' => $pivotA->id,
        'file_path' => 'entregas/ajena/v1.pdf',
    ]);

    $this->actingAs($estudianteB)
        ->deleteJson("/api/entregas/{$this->entrega->id}/versiones/{$version->id}")
        ->assertStatus(404);

    // La versión y su archivo permanecen intactos.
    expect(VersionDocumento::find($version->id))->not->toBeNull();
    expect(Storage::disk('public')->exists('entregas/ajena/v1.pdf'))->toBeTrue();
});

/**
 * Issue #46 — la eliminación debe quedar registrada en audit_logs
 * (acción `version.deleted`) con el identificador de la versión, la entrega
 * y el proyecto al que pertenece.
 */
it('registra en audit_logs la eliminación de la versión', function () {
    Storage::disk('public')->put('entregas/audit/v1.pdf', 'contenido');
    $version = crearVersionEliminable($this->entrega, ['file_path' => 'entregas/audit/v1.pdf']);

    $this->actingAs($this->estudiante)
        ->deleteJson("/api/entregas/{$this->entrega->id}/versiones/{$version->id}")
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->estudiante->id,
        'action' => 'version.deleted',
    ]);
});