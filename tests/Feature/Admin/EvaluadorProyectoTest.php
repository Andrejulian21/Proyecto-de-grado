<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;
use App\Models\Entrega;
use App\Models\EvaluacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    it('unique compuesto proyecto_id + evaluador_id + fase en evaluador_proyecto', function () {
        $coordinador = User::factory()->coordinador()->create();
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $evaluador = User::factory()->external()->create();

        // Issue #51 — Defect 2: the UNIQUE is now (proyecto, evaluador, fase).
        // The same evaluator MAY be assigned to the same project once per phase,
        // so a duplicate is only rejected when the fase matches too.
        EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
        ]);

        // Same evaluator + same project + SAME fase → unique violation.
        expect(fn () => EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
        ]))->toThrow(QueryException::class);

        // Same evaluator + same project + DIFFERENT fase → legitimate, allowed.
        EvaluadorProyecto::create([
            'proyecto_id' => $proyecto->id,
            'evaluador_id' => $evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'presentacion_final',
        ]);

        expect(EvaluadorProyecto::query()->count())->toBe(2);
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
        $this->entrega = Entrega::create(['semester_id' => $this->semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $this->entrega->proyectos()->attach($this->proyecto->id);
    });

    it('coordinador puede listar evaluadores asignados', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/evaluador-proyecto?proyecto_id='.$this->proyecto->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data')[0]['evaluador_principal_id'])->toBe($this->evaluador->id);
    });

    it('coordinador puede asignar evaluador a proyecto', function () {
        $evaluador2 = User::factory()->external()->create(['password_changed_at' => now()]);
        $response = $this->actingAs($this->coordinador)
            ->postJson('/api/admin/evaluador-proyecto', [
                'proyecto_id' => $this->proyecto->id,
                'evaluador_ids' => [$this->evaluador->id, $evaluador2->id],
                'fecha' => '2026-06-15',
                'hora_inicio' => '09:00',
                'hora_fin' => '11:00',
                'fase' => 'presentacion_final',
            ]);

        $response->assertCreated();
        expect(EvaluadorProyecto::count())->toBe(2);
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
                'evaluador_ids' => [$this->evaluador->id],
                'fecha' => '2026-06-15',
                'hora_inicio' => '09:00',
                'hora_fin' => '11:00',
                'fase' => 'presentacion_final',
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
            ->deleteJson('/api/admin/evaluador-proyecto/'.$asignacion->id);

        $response->assertOk();
        expect(EvaluadorProyecto::count())->toBe(0);
    });
});

// =========================================================================
// T-016b — PUT update: regresión edición de asignación (bug fix id)
// =========================================================================

describe('T-016b: update asignacion evaluador-proyecto (bug fix)', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->evaluador2 = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
    });

    it('el index expone el assignment_id real de la fila para editar', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador2->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/evaluador-proyecto?proyecto_id='.$this->proyecto->id);

        $response->assertOk();
        expect($response->json('data.0.assignment_id'))->toBe($asignacion->id);
        expect($response->json('data.0.evaluadores_list.0.assignment_id'))->toBe($asignacion->id);
    });

    it('actualiza fecha/hora/fase de una asignación con el payload del modal', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador2->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fase' => 'presentacion_final',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertOk();
        expect($response->json('data.fase'))->toBe('presentacion_final');
        expect($response->json('data.fecha'))->toBe('2026-07-20');
        expect($response->json('data.assignment_id'))->toBe($asignacion->id);
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'fase' => 'presentacion_final',
            'fecha' => '2026-07-20',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
        ]);
    });

    it('devuelve 404 con error en español cuando la asignación no existe', function () {
        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/999999', [
                'fase' => 'presentacion_final',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(404);
        expect($response->json('error'))->toBe('Asignación no encontrada.');
    });
});

// =========================================================================
// T-016c — PUT update: cambiar evaluadores y fase (nueva capacidad)
// =========================================================================

describe('T-016c: update cambia evaluadores y fase', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->evaluador2 = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->evaluador3 = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
    });

    it('reemplaza el set de evaluadores cuando no hay evaluaciones', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.EvaluadorProyecto::first()->id, [
                'evaluador_ids' => [$this->evaluador2->id, $this->evaluador3->id],
                'fase' => 'presentacion_final',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertOk();
        expect($response->json('data.evaluadores_list'))->toHaveCount(2);
        expect(collect($response->json('data.evaluadores_list'))->pluck('id')->all())
            ->toEqualCanonicalizing([$this->evaluador2->id, $this->evaluador3->id]);
        expect($response->json('data.fase'))->toBe('presentacion_final');
        $this->assertDatabaseMissing('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
        ]);
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador3->id,
            'fase' => 'presentacion_final',
            'fecha' => '2026-07-20',
        ]);
    });

    it('no permite cambiar evaluadores si ya existe una evaluación', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluacionEvaluador::create([
            'evaluador_proyecto_id' => $asignacion->id,
            'nota' => 4.5,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'evaluador_ids' => [$this->evaluador2->id, $this->evaluador3->id],
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(422);
        expect($response->json('error'))
            ->toBe('No se puede modificar la asignación porque ya hay evaluaciones realizadas.');
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
        ]);
    });

    it('no permite cambiar la fase si la asignación ya fue evaluada', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
            'evaluado' => true,
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fase' => 'presentacion_final',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(422);
        expect($response->json('error'))
            ->toBe('No se puede modificar la asignación porque ya hay evaluaciones realizadas.');
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'fase' => 'presentacion_anteproyecto',
        ]);
    });

    it('permite cambiar solo fecha/hora si ya hay evaluaciones', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador2->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluacionEvaluador::create([
            'evaluador_proyecto_id' => $asignacion->id,
            'nota' => 4.5,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fecha' => '2026-07-21',
                'hora_inicio' => '14:00',
                'hora_fin' => '16:00',
            ]);

        $response->assertOk();
        expect($response->json('data.fecha'))->toBe('2026-07-21');
        expect($response->json('data.evaluadores_list'))->toHaveCount(2);
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'fecha' => '2026-07-21',
        ]);
    });

    it('permite cambiar fecha/hora si se mantienen evaluadores y fase con evaluaciones', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador2->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluacionEvaluador::create([
            'evaluador_proyecto_id' => $asignacion->id,
            'nota' => 4.5,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'evaluador_ids' => [$this->evaluador->id, $this->evaluador2->id],
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-22',
                'hora_inicio' => '15:00',
                'hora_fin' => '17:00',
            ]);

        $response->assertOk();
        expect($response->json('data.fecha'))->toBe('2026-07-22');
        expect($response->json('data.evaluadores_list'))->toHaveCount(2);
    });

    it('valida mínimo 2 evaluadores al actualizar', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'evaluador_ids' => [$this->evaluador->id],
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(422);
        expect($response->json('errors.evaluador_ids'))->not->toBeNull();
    });

    it('valida que el evaluador exista al actualizar', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'evaluador_ids' => [999999, $this->evaluador2->id],
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(422);
    });

    it('no permite asignar al director del propio proyecto como evaluador', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'evaluador_ids' => [$this->director->id, $this->evaluador2->id],
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(422);
        expect($response->json('error'))
            ->toBe('Un director no puede evaluar su propio proyecto.');
    });

    it('rechaza actualización que se superpone con otra asignación', function () {
        $directorB = User::factory()->director()->create();
        $proyectoB = Proyecto::create([
            'title' => 'Proyecto B',
            'semester_id' => $this->semestre->id,
            'director_id' => $directorB->id,
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $proyectoB->id,
            'evaluador_id' => $this->evaluador3->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-07-20',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);

        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-06-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-20',
                'hora_inicio' => '10:00',
                'hora_fin' => '12:00',
            ]);

        $response->assertStatus(422);
        expect($response->json('error'))->toContain('se superpone');
    });

    it('permite actualizar al propio horario sin conflicto consigo mismo', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-07-20',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador2->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fecha' => '2026-07-20',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'fase' => 'presentacion_anteproyecto',
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fase' => 'presentacion_anteproyecto',
                'fecha' => '2026-07-20',
                'hora_inicio' => '14:00',
                'hora_fin' => '16:00',
            ]);

        $response->assertOk();
        expect($response->json('data.hora_inicio'))->toBe('14:00');
    });
});

// =========================================================================
// T-016d — Validación de fase canónica (alineación a FaseProyecto)
// =========================================================================

describe('T-016d: fase canónica obligatoria en asignación de evaluadores', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->evaluador2 = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
    });

    it('crea asignación con fase canónica presentacion_anteproyecto', function () {
        $response = $this->actingAs($this->coordinador)
            ->postJson('/api/admin/evaluador-proyecto', [
                'proyecto_id' => $this->proyecto->id,
                'evaluador_ids' => [$this->evaluador->id, $this->evaluador2->id],
                'fecha' => '2026-06-15',
                'hora_inicio' => '09:00',
                'hora_fin' => '11:00',
                'fase' => 'presentacion_anteproyecto',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'fase' => 'presentacion_anteproyecto',
        ]);
    });

    it('rechaza fase legacy Anteproyecto en create (422)', function () {
        $response = $this->actingAs($this->coordinador)
            ->postJson('/api/admin/evaluador-proyecto', [
                'proyecto_id' => $this->proyecto->id,
                'evaluador_ids' => [$this->evaluador->id, $this->evaluador2->id],
                'fecha' => '2026-06-15',
                'hora_inicio' => '09:00',
                'hora_fin' => '11:00',
                'fase' => 'Anteproyecto',
            ]);

        $response->assertStatus(422);
        expect($response->json('errors.fase'))->not->toBeNull();
    });

    it('rechaza fase legacy Final en create (422)', function () {
        $response = $this->actingAs($this->coordinador)
            ->postJson('/api/admin/evaluador-proyecto', [
                'proyecto_id' => $this->proyecto->id,
                'evaluador_ids' => [$this->evaluador->id, $this->evaluador2->id],
                'fecha' => '2026-06-15',
                'hora_inicio' => '09:00',
                'hora_fin' => '11:00',
                'fase' => 'Final',
            ]);

        $response->assertStatus(422);
        expect($response->json('errors.fase'))->not->toBeNull();
    });

    it('rechaza fase legacy Final en update (422)', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fase' => 'Final',
            ]);

        $response->assertStatus(422);
        expect($response->json('errors.fase'))->not->toBeNull();
    });

    it('acepta fase canónica presentacion_final en update', function () {
        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
        ]);
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador2->id,
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
            'fase' => 'presentacion_anteproyecto',
        ]);

        $response = $this->actingAs($this->coordinador)
            ->putJson('/api/admin/evaluador-proyecto/'.$asignacion->id, [
                'fase' => 'presentacion_final',
            ]);

        $response->assertOk();
        expect($response->json('data.fase'))->toBe('presentacion_final');
        $this->assertDatabaseHas('evaluador_proyecto', [
            'proyecto_id' => $this->proyecto->id,
            'fase' => 'presentacion_final',
        ]);
    });
});
