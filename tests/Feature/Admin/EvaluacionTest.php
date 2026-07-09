<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =========================================================================
// T-015 — Migraciones + Modelos
// =========================================================================

describe('T-015: Migraciones y modelos', function () {

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

    it('crea evaluacion con campos correctos', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['proyecto_id' => $proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $evaluador = User::factory()->external()->create();

        $evaluacion = Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
            'grade' => 85.00,
            'comment' => 'Buen trabajo',
            'evaluated_at' => now(),
        ]);

        expect($evaluacion->id)->toBeInt();
        expect($evaluacion->entrega_id)->toBe($entrega->id);
        expect($evaluacion->evaluador_id)->toBe($evaluador->id);
        expect($evaluacion->criterio)->toBe('Estructura');
        expect((float) $evaluacion->percentage)->toEqual(50.00);
        expect((float) $evaluacion->grade)->toEqual(85.00);
        expect($evaluacion->comment)->toBe('Buen trabajo');
        expect($evaluacion->evaluated_at)->not->toBeNull();
    });

    it('unique compuesto entrega_id + evaluador_id + criterio en evaluaciones', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['proyecto_id' => $proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $evaluador = User::factory()->external()->create();

        Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
        ]);

        expect(fn () => Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
        ]))->toThrow(Illuminate\Database\QueryException::class);
    });

    it('evaluacion cascade delete con entrega', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['proyecto_id' => $proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $evaluador = User::factory()->external()->create();

        Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 100.00,
        ]);

        $entrega->delete();

        expect(Evaluacion::count())->toBe(0);
    });

    it('evaluacion creacion con grade nullable', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['proyecto_id' => $proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $evaluador = User::factory()->external()->create();

        $evaluacion = Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 100.00,
        ]);

        expect($evaluacion->grade)->toBeNull();
        expect($evaluacion->comment)->toBeNull();
        expect($evaluacion->evaluated_at)->toBeNull();
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

    it('evaluacion belongsTo relations', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['proyecto_id' => $proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $evaluador = User::factory()->external()->create();

        $evaluacion = Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 100.00,
        ]);

        expect($evaluacion->entrega)->toBeInstanceOf(Entrega::class);
        expect($evaluacion->evaluador)->toBeInstanceOf(User::class);
    });
});

// =========================================================================
// T-016 — CRUD evaluaciones + asignación
// =========================================================================

describe('T-016: CRUD evaluaciones y asignacion', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
        $this->entrega = Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
    });

    // -- Asignación evaluador-proyecto -----------------------------------

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
        expect($response->json('data')[0]['evaluador_id'])->toBe($this->evaluador->id);
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

    // -- Evaluaciones CRUD -----------------------------------------------

    it('evaluador puede listar evaluaciones de una entrega', function () {
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
            'grade' => 85.00,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluaciones?entrega_id=' . $this->entrega->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('evaluador ve SOLO sus evaluaciones', function () {
        $otroEvaluador = User::factory()->external()->create();

        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
            'grade' => 85.00,
            'evaluated_at' => now(),
        ]);
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $otroEvaluador->id,
            'criterio' => 'Contenido',
            'percentage' => 50.00,
            'grade' => 90.00,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluaciones?entrega_id=' . $this->entrega->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('coordinador ve TODAS las evaluaciones', function () {
        $otroEvaluador = User::factory()->external()->create();

        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
            'grade' => 85.00,
            'evaluated_at' => now(),
        ]);
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $otroEvaluador->id,
            'criterio' => 'Contenido',
            'percentage' => 50.00,
            'grade' => 90.00,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/evaluaciones?entrega_id=' . $this->entrega->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('evaluador puede calificar (POST evaluaciones)', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $this->entrega->id,
                'criterio' => 'Estructura',
                'percentage' => 100.00,
                'grade' => 90.00,
                'comment' => 'Excelente estructura',
            ]);

        $response->assertCreated();
        expect(Evaluacion::count())->toBe(1);
        expect($response->json('data')['criterio'])->toBe('Estructura');
    });

    it('calificar evaluacion valida campos requeridos', function () {
        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', []);

        $response->assertStatus(422);
    });

    it('evaluador no puede calificar entrega no asignada (403)', function () {
        $otroProyecto = Proyecto::create(['title' => 'Otro', 'semester_id' => $this->semestre->id]);
        $otraEntrega = Entrega::create(['proyecto_id' => $otroProyecto->id, 'phase' => 'anteproyecto', 'title' => 'Otra entrega', 'due_date' => '2026-03-01']);

        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $otraEntrega->id,
                'criterio' => 'Estructura',
                'percentage' => 100.00,
                'grade' => 90.00,
            ]);

        $response->assertStatus(403);
    });
});

// =========================================================================
// T-017 — Evaluación por criterio
// =========================================================================

describe('T-017: Evaluacion por criterio', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
        $this->entrega = Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
    });

    it('valida que suma de porcentajes por entrega sea 100%', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
        ]);

        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $this->entrega->id,
                'criterio' => 'Contenido',
                'percentage' => 60.00,
            ]);

        $response->assertStatus(422);
        expect($response->json('errors'))->toHaveKey('percentage');
    });

    it('permite crear si suma de porcentajes es exactamente 100%', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
        ]);

        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $this->entrega->id,
                'criterio' => 'Contenido',
                'percentage' => 50.00,
            ]);

        $response->assertCreated();
    });

    it('GET consolidado devuelve promedio ponderado por criterio', function () {
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 40.00,
            'grade' => 80.00,
            'evaluated_at' => now(),
        ]);
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Contenido',
            'percentage' => 60.00,
            'grade' => 90.00,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson("/api/evaluaciones/{$this->entrega->id}/consolidado");

        $response->assertOk();
        $data = $response->json('data');
        expect($data)->toHaveKey('promedio_ponderado');
        // (40*80 + 60*90) / 100 = (3200 + 5400) / 100 = 86
        expect((float) $data['promedio_ponderado'])->toEqual(86.00);
        expect($data['criterios'])->toHaveCount(2);
    });

    it('GET consolidado con evaluaciones sin nota las excluye del calculo', function () {
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 100.00,
            // grade = null, no evaluado aun
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson("/api/evaluaciones/{$this->entrega->id}/consolidado");

        $response->assertOk();
        expect($response->json('data')['promedio_ponderado'])->toBeNull();
    });
});

// =========================================================================
// T-018 — Reporte consolidado
// =========================================================================

describe('T-018: Reporte consolidado', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
        $this->proyecto->estudiantes()->attach($this->estudiante);
        $this->entrega = Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);

        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 40.00,
            'grade' => 80.00,
            'evaluated_at' => now(),
        ]);
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Contenido',
            'percentage' => 60.00,
            'grade' => 90.00,
            'evaluated_at' => now(),
        ]);
    });

    it('coordinador puede ver reporte consolidado de proyecto', function () {
        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id=' . $this->proyecto->id);

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveKey('proyecto');
        expect($data)->toHaveKey('estudiantes');
        expect($data)->toHaveKey('director');
        expect($data)->toHaveKey('entregas');
        expect($data)->toHaveKey('promedio_general');
        expect($data)->toHaveKey('estado');

        expect($data['proyecto']['id'])->toBe($this->proyecto->id);
        expect($data['proyecto']['title'])->toBe('Proyecto Test');
        expect($data['estudiantes'])->toHaveCount(1);
        expect($data['director']['id'])->toBe($this->director->id);
    });

    it('reporte consolidado calcula promedio general correctamente', function () {
        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id=' . $this->proyecto->id);

        $response->assertOk();
        $data = $response->json('data');

        // (40*80 + 60*90) / 100 = 86
        expect((float) $data['promedio_general'])->toEqual(86.00);
        expect($data['entregas'][0]['promedio_ponderado'])->toEqual(86.00);
    });

    it('no-coordinador NO puede ver reporte consolidado (403)', function () {
        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id=' . $this->proyecto->id);

        $response->assertStatus(403);
    });

    it('reporte consolidado requiere proyecto_id', function () {
        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado');

        $response->assertStatus(422);
    });
});
