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

/**
 * DELETE /api/admin/proyectos/{id}
 *
 * Issue #40: la regla "no eliminar proyecto con versiones subidas" no tenía
 * test. El controller verifica el pivote entrega_proyecto antes de eliminar.
 * Desde la fase 3 (drop de entregas.proyecto_id) el vínculo entrega-proyecto
 * solo existe en el pivote; el caso legacy por FK directa ya no aplica.
 */
beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
});

it('coordinador elimina un proyecto sin versiones subidas', function () {
    $proyecto = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/proyectos/{$proyecto->id}");

    $response->assertOk()
        ->assertJson(['message' => 'Proyecto eliminado correctamente.']);

    expect(Proyecto::find($proyecto->id))->toBeNull();
});

it('no elimina un proyecto cuyas entregas tienen versiones subidas (422)', function () {
    $proyecto = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);

    // Production shape: entrega del semestre vinculada por pivote.
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega con versión',
        'due_date' => '2026-09-15',
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ]);
    $entrega->proyectos()->attach($proyecto->id);

    $pivot = EntregaProyecto::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'version_number' => 1,
        'file_path' => 'entregas/v1.pdf',
        'file_size' => 1024,
        'original_name' => 'v1.pdf',
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/proyectos/{$proyecto->id}");

    $response->assertStatus(422)
        ->assertJson(['error' => 'No se puede eliminar el proyecto porque ya tiene entregas con versiones subidas.']);

    expect(Proyecto::find($proyecto->id))->not->toBeNull();
});
