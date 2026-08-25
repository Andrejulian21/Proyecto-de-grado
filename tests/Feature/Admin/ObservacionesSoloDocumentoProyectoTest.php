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
 * @param  array<int, array<string, mixed>>  $archivos
 * @return array{entrega: Entrega, version: VersionDocumento, pivot: EntregaProyecto}
 */
function crearEntregaConVersion(Proyecto $proyecto, array $archivos, string $archivoId, int $versionNumber = 1): array
{
    $entrega = Entrega::create([
        'semester_id' => $proyecto->semester_id,
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
        'version_number' => $versionNumber,
        'file_path' => "entregas/test-v{$versionNumber}.pdf",
        'file_size' => 1024,
        'original_name' => "test-v{$versionNumber}.pdf",
        'uploaded_at' => now(),
    ]);

    return ['entrega' => $entrega, 'version' => $version, 'pivot' => $pivot];
}

it('persiste observaciones en versiones de cualquier documento solicitado', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'planteamiento', 'nombre' => 'Planteamiento', 'versionamiento' => true],
            ['slug' => 'anexo-1', 'nombre' => 'Anexo', 'versionamiento' => false],
        ],
        'anexo-1',
    );

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Revisar las referencias utilizadas',
            'version_id' => $version->id,
        ])
        ->assertOk();

    expect($version->fresh()->director_notes)->toBe('Revisar las referencias utilizadas');
});

it('persiste observaciones independientes por version del mismo documento', function () {
    $archivos = [
        ['slug' => 'marco_teorico', 'nombre' => 'Marco teórico', 'versionamiento' => true],
    ];

    ['entrega' => $entrega, 'version' => $v1, 'pivot' => $pivot] = crearEntregaConVersion(
        $this->proyecto,
        $archivos,
        'marco_teorico',
        1,
    );

    $v2 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'marco_teorico',
        'version_number' => 2,
        'file_path' => 'entregas/test-v2.pdf',
        'file_size' => 2048,
        'original_name' => 'test-v2.pdf',
        'uploaded_at' => now()->addDay(),
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Revisar las referencias utilizadas',
            'version_id' => $v1->id,
        ])
        ->assertOk();

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Mejoró la estructura, pero falta el estado del arte',
            'version_id' => $v2->id,
        ])
        ->assertOk();

    expect($v1->fresh()->director_notes)->toBe('Revisar las referencias utilizadas')
        ->and($v2->fresh()->director_notes)->toBe('Mejoró la estructura, pero falta el estado del arte');
});

it('el estudiante consulta la observacion de cada version sin reutilizar otra', function () {
    $estudiante = User::factory()->create();
    $this->proyecto->estudiantes()->attach($estudiante);

    $archivos = [
        ['slug' => 'marco_teorico', 'nombre' => 'Marco teórico', 'versionamiento' => true],
    ];

    ['entrega' => $entrega, 'version' => $v1, 'pivot' => $pivot] = crearEntregaConVersion(
        $this->proyecto,
        $archivos,
        'marco_teorico',
        1,
    );

    $v2 = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'marco_teorico',
        'version_number' => 2,
        'file_path' => 'entregas/test-v2.pdf',
        'file_size' => 2048,
        'original_name' => 'test-v2.pdf',
        'uploaded_at' => now()->addDay(),
        'director_notes' => null,
    ]);

    $v1->update(['director_notes' => 'Observación de la versión 1']);

    $response = $this->actingAs($estudiante)->getJson('/api/estudiante/entregas');
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('id', $entrega->id);
    $porNumero = collect($item['versiones'])->keyBy('numero_version');

    expect($porNumero[1]['observacion'])->toBe('Observación de la versión 1')
        ->and($porNumero[2]['observacion'])->toBeNull();
});

it('persiste observaciones en versiones del documento-proyecto', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaConVersion(
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
});

it('persiste observaciones cuando el documento no tiene versionamiento', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'carta_aval', 'nombre' => 'Carta de aval', 'versionamiento' => false],
        ],
        'carta_aval',
    );

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'revisada',
            'director_notes' => 'Ajustes requeridos',
            'version_id' => $version->id,
        ])
        ->assertOk();

    expect($version->fresh()->director_notes)->toBe('Ajustes requeridos');
});

it('persiste observaciones en versiones legacy sin archivo_requerido_id', function () {
    ['entrega' => $entrega, 'version' => $version] = crearEntregaConVersion(
        $this->proyecto,
        [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
        'documento-proyecto',
    );

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
});
