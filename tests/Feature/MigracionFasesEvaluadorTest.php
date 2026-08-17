<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Ruta absoluta de la migración de alineación de fases.
 */
function migracionFasesPath(): string
{
    return base_path('database/migrations/2026_08_17_000001_align_evaluador_proyecto_fase_to_canonical.php');
}

/**
 * Instancia de la migración (clase anónima) para invocar up()/down().
 */
function migracionFases(): Migration
{
    return require migracionFasesPath();
}

beforeEach(function () {
    $this->director = User::factory()->director()->create();
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
});

/**
 * Crea una fila evaluador_proyecto con la fase dada y devuelve su id.
 */
function crearAsignacionConFase(mixed $scope, string $fase): int
{
    $evaluador = User::factory()->external()->create();

    $asignacion = EvaluadorProyecto::create([
        'proyecto_id' => $scope->proyecto->id,
        'evaluador_id' => $evaluador->id,
        'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
        'assigned_at' => now(),
        'fase' => $fase,
    ]);

    return (int) $asignacion->id;
}

it('convierte filas legacy Anteproyecto/Final a fases canónicas en up()', function () {
    $idAnteproyecto = crearAsignacionConFase($this, 'Anteproyecto');
    $idFinal = crearAsignacionConFase($this, 'Final');
    // Una fila ya canónica no debe tocarse.
    $idCanonico = crearAsignacionConFase($this, 'presentacion_anteproyecto');

    migracionFases()->up();

    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idAnteproyecto,
        'fase' => 'presentacion_anteproyecto',
    ]);
    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idFinal,
        'fase' => 'presentacion_final',
    ]);
    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idCanonico,
        'fase' => 'presentacion_anteproyecto',
    ]);
});

it('revierte fases canónicas a legacy en down()', function () {
    $idAnteproyecto = crearAsignacionConFase($this, 'presentacion_anteproyecto');
    $idFinal = crearAsignacionConFase($this, 'presentacion_final');

    migracionFases()->down();

    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idAnteproyecto,
        'fase' => 'Anteproyecto',
    ]);
    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idFinal,
        'fase' => 'Final',
    ]);
});

it('es idempotente en up() sobre datos ya canónicos', function () {
    $idAnteproyecto = crearAsignacionConFase($this, 'presentacion_anteproyecto');
    $idFinal = crearAsignacionConFase($this, 'presentacion_final');

    migracionFases()->up();
    migracionFases()->up();

    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idAnteproyecto,
        'fase' => 'presentacion_anteproyecto',
    ]);
    $this->assertDatabaseHas('evaluador_proyecto', [
        'id' => $idFinal,
        'fase' => 'presentacion_final',
    ]);
});
