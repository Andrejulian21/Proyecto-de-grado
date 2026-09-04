<?php

declare(strict_types=1);

use App\Actions\Entrega\StoreEntregaAction;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        $this->entrega = Entrega::create(['semester_id' => $this->semestre->id, 'phase' => 'anteproyecto', 'title' => 'Entrega 1', 'due_date' => '2026-03-01']);
        $this->entrega->proyectos()->attach($this->proyecto->id);

        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 40.00,
            'grade' => 4.00,
            'evaluated_at' => now(),
        ]);
        Evaluacion::create([
            'entrega_id' => $this->entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Contenido',
            'percentage' => 60.00,
            'grade' => 4.50,
            'evaluated_at' => now(),
        ]);
    });

    it('coordinador puede ver reporte consolidado de proyecto', function () {
        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id='.$this->proyecto->id);

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

    it('reporte consolidado devuelve entregas creadas por StoreEntregaAction', function () {
        // Acceptance (issue #48): the report must surface entregas created
        // through the production path. StoreEntregaAction links the entrega
        // to active projects via the pivot, so the report must read them from
        // entregasPivot (there is no entregas.proyecto_id anymore, #39).
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

        Evaluacion::create([
            'entrega_id' => $entrega->id,
            'evaluador_id' => $this->evaluador->id,
            'criterio' => 'Estructura',
            'percentage' => 100.00,
            'grade' => 4.30,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id='.$this->proyecto->id);

        $response->assertOk();
        $entregas = collect($response->json('data.entregas'));
        expect($entregas->contains('id', $entrega->id))->toBeTrue();
        expect((float) $entregas->firstWhere('id', $entrega->id)['promedio_ponderado'])->toEqual(4.30);
    });

    it('reporte consolidado calcula promedio general correctamente', function () {
        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id='.$this->proyecto->id);

        $response->assertOk();
        $data = $response->json('data');

        // (40*4.00 + 60*4.50) / 100 = 4.30
        expect((float) $data['promedio_general'])->toEqual(4.30);
        expect($data['entregas'][0]['promedio_ponderado'])->toEqual(4.30);
    });

    it('no-coordinador NO puede ver reporte consolidado (403)', function () {
        $response = $this->actingAs($this->evaluador)
            ->getJson('/api/admin/reportes/consolidado?proyecto_id='.$this->proyecto->id);

        $response->assertStatus(403);
    });

    it('reporte consolidado requiere proyecto_id', function () {
        $response = $this->actingAs($this->coordinador)
            ->getJson('/api/admin/reportes/consolidado');

        $response->assertStatus(422);
    });
});
