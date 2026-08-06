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
 * Seed an active entrega with a file configuration and one version belonging
 * to the given archivo_requerido_id (RF-SUP-01/02).
 *
 * @param  array<int, array<string, mixed>>  $archivos
 * @return array{entrega: Entrega, version: VersionDocumento, pivot: EntregaProyecto}
 */
function crearEntregaConVersion(Proyecto $proyecto, array $archivos, string $archivoId): array
{
    $entrega = Entrega::create([
        'proyecto_id' => $proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega para revisar',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
        'archivos_requeridos' => $archivos,
    ]);

    $pivot = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $proyecto->id,
        'estado' => 'pendiente',
    ]);

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => $archivoId,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'test.pdf',
        'uploaded_at' => now(),
    ]);

    return ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot];
}

// -- RF-SUP-01: observaciones solo en versiones del documento-proyecto --------

it('no persiste observaciones en versiones de archivos que no son documento-proyecto', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
            ['slug' => 'anexo-1', 'nombre' => 'Anexo', 'versionamiento' => false],
        ],
        'anexo-1',
    );

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Observación fuera de lugar',
            'version_id' => $version->id,
            'director_grade' => 4.0,
        ])
        ->assertOk();

    expect($version->fresh()->director_notes)->toBeNull();
    expect($pivot->fresh()->observaciones_director)->toBeNull();
});

it('persiste observaciones en versiones del documento-proyecto', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
        'documento-proyecto',
    );

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Excelente documento',
            'version_id' => $version->id,
            'director_grade' => 4.0,
        ])
        ->assertOk();

    expect($version->fresh()->director_notes)->toBe('Excelente documento');
    expect($pivot->fresh()->observaciones_director)->toBe('Excelente documento');
});

// -- RF-SUP-02: archivos sin versionamiento nunca llevan observaciones --------

it('no persiste observaciones cuando documento-proyecto no tiene versionamiento', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => false],
        ],
        'documento-proyecto',
    );

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Ajustes requeridos',
            'version_id' => $version->id,
        ])
        ->assertOk();

    expect($version->fresh()->director_notes)->toBeNull();
    expect($pivot->fresh()->observaciones_director)->toBeNull();
});

// -- Legacy: versiones sin archivo_requerido_id conservan el comportamiento ----

it('persiste observaciones en versiones legacy sin archivo_requerido_id', function () {
    ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
        'documento-proyecto',
    );

    // Simulate a legacy version created before the per-file system.
    $version->update(['archivo_requerido_id' => null]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Comportamiento histórico',
            'version_id' => $version->id,
            'director_grade' => 4.0,
        ])
        ->assertOk();

    expect($version->fresh()->director_notes)->toBe('Comportamiento histórico');
    expect($pivot->fresh()->observaciones_director)->toBe('Comportamiento histórico');
});
