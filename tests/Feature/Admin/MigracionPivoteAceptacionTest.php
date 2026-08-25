<?php

declare(strict_types=1);

use App\Actions\Entrega\StoreEntregaAction;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use App\Models\Notificacion;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =========================================================================
// Issue #39 — acceptance criteria exercised through StoreEntregaAction.
// The entrega_proyecto pivot is the single source of truth; the legacy
// entregas.proyecto_id column is gone.
// =========================================================================

function crearEntregaViaAction(Semestre $semestre, array $overrides = []): Entrega
{
    return app(StoreEntregaAction::class)->handle(array_merge([
        'grupo_id' => $semestre->id,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega plantilla',
        'descripcion' => 'Descripcion',
        'fecha_limite' => now()->addMonths(2)->toDateString(),
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
        ],
    ], $overrides));
}

function seedEvaluacionContexto(): array
{
    $coordinador = User::factory()->coordinador()->create();
    $director = User::factory()->director()->create();
    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $semestre = Semestre::factory()->create(['is_active' => true]);
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    $proyecto->estudiantes()->attach($estudiante);
    $entrega = crearEntregaViaAction($semestre);
    EvaluadorProyecto::factory()->create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
    ]);

    return compact('coordinador', 'director', 'evaluador', 'estudiante', 'semestre', 'proyecto', 'entrega');
}

describe('EvaluacionController sobre entregas creadas con StoreEntregaAction', function () {

    it('un evaluador asignado a un proyecto del pivote puede calificar (201)', function () {
        $ctx = seedEvaluacionContexto();

        $response = $this->actingAs($ctx['evaluador'])
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $ctx['entrega']->id,
                'criterio' => 'Estructura',
                'percentage' => 40,
                'grade' => 4.0,
                'comment' => 'Bien',
            ]);

        $response->assertStatus(201);
        expect(Evaluacion::where('entrega_id', $ctx['entrega']->id)->count())->toBe(1);
    });

    it('un evaluador NO asignado a ningún proyecto del pivote recibe 403', function () {
        $ctx = seedEvaluacionContexto();
        $ajeno = User::factory()->external()->create(['password_changed_at' => now()]);

        $response = $this->actingAs($ajeno)
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $ctx['entrega']->id,
                'criterio' => 'Estructura',
                'percentage' => 40,
            ]);

        $response->assertStatus(403);
    });

    it('el listado agrupa las evaluaciones por proyecto real del pivote (no por clave 0)', function () {
        $ctx = seedEvaluacionContexto();

        $this->actingAs($ctx['evaluador'])
            ->postJson('/api/evaluaciones', [
                'entrega_id' => $ctx['entrega']->id,
                'criterio' => 'Estructura',
                'percentage' => 40,
                'grade' => 4.0,
            ])->assertStatus(201);

        $response = $this->actingAs($ctx['evaluador'])
            ->getJson('/api/evaluaciones');

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->not->toBeEmpty();
        expect($data[0]['proyecto_id'])->toBe($ctx['proyecto']->id);
        expect($data[0]['proyecto_nombre'])->toBe($ctx['proyecto']->title);
        expect((float) $data[0]['puntuaciones'][0])->toEqual(4.0);
    });
});

describe('ReporteController sobre entregas creadas con StoreEntregaAction', function () {

    it('el reporte consolidado devuelve las entregas del pivote y su promedio', function () {
        $ctx = seedEvaluacionContexto();

        Evaluacion::create([
            'entrega_id' => $ctx['entrega']->id,
            'evaluador_id' => $ctx['evaluador']->id,
            'criterio' => 'Estructura',
            'percentage' => 40,
            'grade' => 80,
            'evaluated_at' => now(),
        ]);
        Evaluacion::create([
            'entrega_id' => $ctx['entrega']->id,
            'evaluador_id' => $ctx['evaluador']->id,
            'criterio' => 'Contenido',
            'percentage' => 60,
            'grade' => 90,
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($ctx['coordinador'])
            ->getJson('/api/admin/reportes/consolidado?proyecto_id='.$ctx['proyecto']->id);

        $response->assertOk();
        $data = $response->json('data');

        expect($data['entregas'])->toHaveCount(1);
        expect((float) $data['promedio_general'])->toEqual(86.00);
        expect($data['entregas'][0]['promedio_ponderado'])->toEqual(86.00);
    });
});

describe('ReviewEntregaAction — notifica SOLO al proyecto revisado (issue #49)', function () {

    it('al revisar una entrega notifica únicamente a los estudiantes del proyecto revisado', function () {
        $director = User::factory()->director()->create();
        $estudianteA = User::factory()->create(['role' => UserRole::Estudiante->value]);
        $estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);

        $semestre = Semestre::factory()->create(['is_active' => true]);
        $proyectoA = Proyecto::factory()->create(['semester_id' => $semestre->id, 'director_id' => $director->id]);
        $proyectoB = Proyecto::factory()->create(['semester_id' => $semestre->id, 'director_id' => $director->id]);
        $proyectoA->estudiantes()->attach($estudianteA);
        $proyectoB->estudiantes()->attach($estudianteB);

        $entrega = crearEntregaViaAction($semestre);

        // The reviewed delivery belongs to project A.
        $pivotA = EntregaProyecto::where('entrega_id', $entrega->id)
            ->where('proyecto_id', $proyectoA->id)
            ->firstOrFail();

        $version = VersionDocumento::create([
            'entrega_id' => $entrega->id,
            'entrega_proyecto_id' => $pivotA->id,
            'version_number' => 1,
            'file_path' => 'entregas/test.pdf',
            'file_size' => 1024,
            'original_name' => 'test.pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($director)
            ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
                'status' => 'aprobada',
                'consolidated_grade' => 4.5,
                'director_notes' => 'Buen trabajo',
                'version_id' => $version->id,
            ])->assertOk();

        expect(Notificacion::where('user_id', $estudianteA->id)->count())->toBe(1);
        expect(Notificacion::where('user_id', $estudianteB->id)->count())->toBe(0);
    });
});