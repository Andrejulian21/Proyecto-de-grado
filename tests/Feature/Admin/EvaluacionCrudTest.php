<?php

declare(strict_types=1);

use App\Actions\Entrega\StoreEntregaAction;
use App\Enums\EstadoInvitacionEvaluador;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =========================================================================
// T-015 — Migraciones + Modelos (Evaluacion)
// =========================================================================

describe('T-015: Migraciones y modelos Evaluacion', function () {

    it('crea evaluacion con campos correctos', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['semester_id' => $semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $entrega->proyectos()->attach($proyecto->id);
        $evaluador = User::factory()->external()->create();

        $evaluacion = Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
            'grade' => 4.50,
            'comment' => 'Buen trabajo',
            'evaluated_at' => now(),
        ]);

        expect($evaluacion->id)->toBeInt();
        expect($evaluacion->entrega_id)->toBe($entrega->id);
        expect($evaluacion->evaluador_id)->toBe($evaluador->id);
        expect($evaluacion->criterio)->toBe('Estructura');
        expect((float) $evaluacion->percentage)->toEqual(50.00);
        expect((float) $evaluacion->grade)->toEqual(4.50);
        expect($evaluacion->comment)->toBe('Buen trabajo');
        expect($evaluacion->evaluated_at)->not->toBeNull();
    });

    it('unique compuesto entrega_id + evaluador_id + criterio en evaluaciones', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['semester_id' => $semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $entrega->proyectos()->attach($proyecto->id);
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
        ]))->toThrow(QueryException::class);
    });

    it('evaluacion cascade delete con entrega', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['semester_id' => $semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $entrega->proyectos()->attach($proyecto->id);
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
        $entrega = Entrega::create(['semester_id' => $semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $entrega->proyectos()->attach($proyecto->id);
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

    it('evaluacion belongsTo relations', function () {
        $director = User::factory()->director()->create();
        $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $proyecto = Proyecto::create(['title' => 'Test', 'semester_id' => $semestre->id, 'director_id' => $director->id]);
        $entrega = Entrega::create(['semester_id' => $semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $entrega->proyectos()->attach($proyecto->id);
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
// T-016 — CRUD evaluaciones
// =========================================================================

describe('T-016: CRUD evaluaciones', function () {

    beforeEach(function () {
        $this->coordinador = User::factory()->coordinador()->create();
        $this->director = User::factory()->director()->create();
        $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
        $this->semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
        $this->proyecto = Proyecto::create(['title' => 'Proyecto Test', 'semester_id' => $this->semestre->id, 'director_id' => $this->director->id]);
        $this->entrega = Entrega::create(['semester_id' => $this->semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $this->entrega->proyectos()->attach($this->proyecto->id);
    });

    it('evaluador puede listar evaluaciones de una entrega', function () {
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 50.00,
            'grade' => 4.50,
            'evaluated_at' => now(),
        ]);

        // Issue #47: an external evaluator lists only projects they are
        // assigned to (via evaluador_proyecto).
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluaciones?entrega_id='.$this->entrega->id);

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

        // Issue #47: the evaluador is assigned to this project, so they may
        // list it; they still only see their own evaluations.
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluaciones?entrega_id='.$this->entrega->id);

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
            ->getJson('/api/evaluaciones?entrega_id='.$this->entrega->id);

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
                'grade' => 4.50,
                'comment' => 'Excelente estructura',
            ]);

        $response->assertCreated();
        expect(Evaluacion::count())->toBe(1);
        expect($response->json('data')['criterio'])->toBe('Estructura');
    });

    it('evaluador asignado recibe 201 al calificar entrega creada por StoreEntregaAction', function () {
        // Acceptance (issue #48): an assigned evaluator must be able to grade
        // an entrega created through the production path. StoreEntregaAction
        // links the entrega to projects via the pivot (no proyecto_id column,
        // dropped in #39), so the assignment check must resolve the project
        // from the pivot.
        $this->proyecto->update(['status' => 'en_curso']);

        $entrega = app(StoreEntregaAction::class)->handle([
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega produccion',
            'descripcion' => 'Descripción',
            'fecha_limite' => '2026-08-15',
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
            ],
        ]);

        // The action attaches the entrega to every active project of the semester.
        expect($entrega->proyectos()->where('proyectos.id', $this->proyecto->id)->exists())->toBeTrue();

        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $entrega->id,
                'criterio' => 'Estructura',
                'percentage' => 100.00,
                'grade' => 4.50,
                'comment' => 'Excelente estructura',
            ]);

        $response->assertCreated();
        expect(Evaluacion::where('entrega_id', $entrega->id)->count())->toBe(1);
    });

    it('calificar evaluacion valida campos requeridos', function () {
        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', []);

        $response->assertStatus(422);
    });

    it('evaluador no puede calificar entrega no asignada (403)', function () {
        $otroProyecto = Proyecto::create(['title' => 'Otro', 'semester_id' => $this->semestre->id]);
        $otraEntrega = Entrega::create(['semester_id' => $this->semestre->id, 'phase' => 'anteproyecto', 'title' => 'Otra entrega', 'due_date' => '2026-03-01']);
        $otraEntrega->proyectos()->attach($otroProyecto->id);

        $response = $this->actingAs($this->evaluador)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $otraEntrega->id,
                'criterio' => 'Estructura',
                'percentage' => 100.00,
                'grade' => 4.50,
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
        $this->entrega = Entrega::create(['semester_id' => $this->semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $this->entrega->proyectos()->attach($this->proyecto->id);
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

        // Issue #47: an external evaluator may see the consolidado only of
        // an entrega linked to a project they are assigned to.
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
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
        ]);

        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson("/api/evaluaciones/{$this->entrega->id}/consolidado");

        $response->assertOk();
        expect($response->json('data')['promedio_ponderado'])->toBeNull();
    });
});
