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

beforeEach(function () {
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $this->director = User::factory()->director()->create();
    $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
    $this->otroEvaluador = User::factory()->external()->create(['password_changed_at' => now()]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Evaluador Demo',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $estudiante = User::factory()->create(['role' => 'Estudiante']);
    $this->proyecto->estudiantes()->attach($estudiante->id);
});

describe('GET /api/evaluador/evaluaciones', function () {
    it('returns only projects assigned to the authenticated evaluator', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'Anteproyecto',
            'fecha' => '2026-07-15',
        ]);

        $otroProyecto = Proyecto::create([
            'title' => 'Proyecto Ajeno',
            'semester_id' => $this->semestre->id,
            'director_id' => $this->director->id,
        ]);

        EvaluadorProyecto::create([
            'proyecto_id' => $otroProyecto->id,
            'evaluador_id' => $this->otroEvaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'Final',
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/evaluaciones');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.id'))->toBe($this->proyecto->id)
            ->and($response->json('data.0.director.name'))->toBe($this->director->name)
            ->and($response->json('data.0.evaluation_status'))->toBe('pending');
    });

    it('returns empty data when evaluator has no assignments', function () {
        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/evaluaciones');

        $response->assertOk()
            ->assertJson(['data' => []]);
    });

    it('marks evaluation as evaluated when grades exist', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'Anteproyecto',
        ]);

        $entrega = Entrega::create([
            'proyecto_id' => $this->proyecto->id,
            'phase' => 'anteproyecto',
            'title' => 'Entrega anteproyecto',
            'due_date' => '2026-03-01',
            'status' => 'aprobada',
        ]);

        Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 100,
            'grade' => 4.2,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/evaluaciones');

        $response->assertOk();
        expect($response->json('data.0.evaluation_status'))->toBe('evaluated')
            ->and($response->json('data.0.rating'))->toBe(4.2);
    });
});

describe('GET /api/evaluador/kpis', function () {
    it('returns coherent KPI counts', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'Anteproyecto',
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/kpis');

        $response->assertOk()
            ->assertJsonPath('data.proyectos_asignados', 1)
            ->assertJsonPath('data.evaluaciones_pendientes', 1)
            ->assertJsonPath('data.evaluaciones_completadas', 0);
    });
});

describe('GET /api/evaluador/proyectos/{id}/entrega-fase', function () {
    it('returns 403 when evaluator is not assigned', function () {
        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/proyectos/'.$this->proyecto->id.'/entrega-fase?fase=Anteproyecto');

        $response->assertStatus(403);
    });

    it('returns 404 when no approved entrega exists for phase', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'Anteproyecto',
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/proyectos/'.$this->proyecto->id.'/entrega-fase?fase=Anteproyecto');

        $response->assertStatus(404);
    });

    it('returns approved entrega with versions when assigned', function () {
        EvaluadorProyecto::create([
            'proyecto_id' => $this->proyecto->id,
            'evaluador_id' => $this->evaluador->id,
            'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
            'assigned_at' => now(),
            'fase' => 'Anteproyecto',
        ]);

        $entrega = Entrega::create([
            'proyecto_id' => $this->proyecto->id,
            'phase' => 'anteproyecto',
            'title' => 'Entrega anteproyecto',
            'due_date' => '2026-03-01',
            'status' => 'aprobada',
        ]);

        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/evaluador/proyectos/'.$this->proyecto->id.'/entrega-fase?fase=Anteproyecto');

        $response->assertOk()
            ->assertJsonPath('data.id', $entrega->id);
    });
});
