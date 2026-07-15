<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =========================================================================
// T-015 — Migraciones + Modelos (EvaluadorProyecto)
// =========================================================================

describe('T-015: Migraciones y modelos EvaluadorProyecto', function () {

    it('crea evaluador_proyecto con campos correctos', function () {
        $coordinador = User::factory()->coordinador()->create();
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $evaluador = User::factory()->external()->create();

        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        expect($asignacion->id)->toBeInt();
        expect($asignacion->proyecto_id)->toBe($proyecto->id);
        expect($asignacion->evaluador_id)->toBe($evaluador->id);
        expect($asignacion->invitation_status)->toBe(EstadoInvitacionEvaluador::Pendiente);
        expect($asignacion->assigned_at)->not->toBeNull();
    });

    it('unique compuesto proyecto_id + evaluador_id en evaluador_proyecto', function () {
        $coordinador = User::factory()->coordinador()->create();
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $evaluador = User::factory()->external()->create();

        EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        expect(fn () => EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]))->toThrow(Illuminate\Database\QueryException::class);
    });

    it('evaluador_proyecto belongsTo relations', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $evaluador = User::factory()->external()->create();

        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        expect($asignacion->proyecto)->toBeInstanceOf(Proyecto::class);
        expect($asignacion->evaluador)->toBeInstanceOf(User::class);
    });
});

// =========================================================================
// T-016 — CRUD evaluaciones + asignación (EvaluadorProyecto part)
// =========================================================================

describe('T-016: CRUD asignacion evaluador-proyecto', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
        $this->entrega = Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
    });

    it('coordinador puede listar evaluadores asignados', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/evaluador-proyecto?proyecto_id=' . $this->proyecto->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data')[0]['evaluador_principal_id'])->toBe($this->evaluador->id);
    });

    it('coordinador puede asignar evaluador a proyecto', function () {
        $response = $this->actingAs($this->coordinador)
            ->postJson('/api/admin/evaluador-proyecto', [
                'proyecto_id' => $this->proyecto->id,
                'evaluador_id' => $this->evaluador->id,
            ]);

        $response->assertCreated();
        expect(EvaluadorProyecto::count())->toBe(1);
    });

    it('asignar evaluador valida campos requeridos', function () {
        $response = $this->actingAs($this->coordinador)
            ->postJson('/api/admin/evaluador-proyecto', []);

        $response->assertStatus(422);
    });

    it('no-evaluador NO puede asignar evaluador (403)', function () {
        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/admin/evaluador-proyecto', [
                'proyecto_id' => $this->proyecto->id,
                'evaluador_id' => $this->evaluador->id,
            ]);

        $response->assertStatus(403);
    });

    it('coordinador puede desasignar evaluador de proyecto', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->deleteJson('/api/admin/evaluador-proyecto?' . http_build_query([
                'proyecto_id' => $this->proyecto->id,
                'evaluador_id' => $this->evaluador->id,
            ]));

        $response->assertOk();
        expect(EvaluadorProyecto::count())->toBe(0);
    });
});


