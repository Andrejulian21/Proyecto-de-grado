<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\EvaluacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a full assignment context: semester, project (with director and
 * one student), external evaluador and the EvaluadorProyecto row.
 *
 * Reuse one Semestre across contexts in the same test so the auto-generated
 * `proyectos.code` (semester name + per-semester count) stays unique.
 *
 * @param  array<string, mixed>  $overrides
 * @param  Semestre|null  $semestre  reuse an existing semester instead of creating one
 * @return array{semestre: Semestre, director: User, proyecto: Proyecto,
 *               estudiante: User, evaluador: User, asignacion: EvaluadorProyecto}
 */
function evaluadorAsignacionContext(array $overrides = [], ?Semestre $semestre = null): array
{
    $semestre ??= Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $director = User::factory()->director()->create();

    $proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);

    $estudiante = User::factory()->create();
    $proyecto->estudiantes()->attach($estudiante->id);

    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);

    $asignacion = EvaluadorProyecto::create(array_merge([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'invitation_status' => 'Aceptada',
        'assigned_at' => now(),
        'fase' => 'Anteproyecto',
    ], $overrides));

    return compact('semestre', 'director', 'proyecto', 'estudiante', 'evaluador', 'asignacion');
}

/**
 * Create the semester delivery (Entrega) the detail endpoint resolves
 * through semester_id + phase, matching the assignment's fase mapping.
 *
 * @param  array<string, mixed>  $context  from evaluadorAsignacionContext()
 * @param  array<string, mixed>  $overrides
 */
function crearEntregaParaEvaluador(array $context, string $phase = 'anteproyecto', array $overrides = []): Entrega
{
    return Entrega::create(array_merge([
        'semester_id' => $context['semestre']->id,
        'phase' => $phase,
        'title' => 'Entrega '.$phase,
        'due_date' => '2026-08-15',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true, 'analizable_ia' => true],
        ],
    ], $overrides));
}

// =========================================================================
// RF-EVA-01 — Cards de proyectos asignados
// =========================================================================

it('lista las asignaciones del evaluador autenticado con su flag evaluado (RF-EVA-01)', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
    $a1 = evaluadorAsignacionContext(['evaluador_id' => $evaluador->id], $semestre);
    $a2 = evaluadorAsignacionContext(['evaluador_id' => $evaluador->id, 'fase' => 'Final'], $semestre);
    $a3 = evaluadorAsignacionContext(['evaluador_id' => $evaluador->id], $semestre);
    $a3['asignacion']->update(['evaluado' => true]);

    $response = $this->actingAs($evaluador)
        ->getJson('/api/evaluador/mis-asignaciones');

    $response->assertOk();
    $items = $response->json('data');
    expect($items)->toHaveCount(3);
    expect(collect($items)->where('evaluado', true))->toHaveCount(1);
    expect(collect($items)->where('evaluado', false))->toHaveCount(2);
    expect(collect($items)->pluck('id')->all())
        ->toEqualCanonicalizing([$a1['asignacion']->id, $a2['asignacion']->id, $a3['asignacion']->id]);
});

it('no expone asignaciones de otros evaluadores (RF-EVA-01)', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $ctx = evaluadorAsignacionContext([], $semestre);
    evaluadorAsignacionContext([], $semestre);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($ctx['asignacion']->id);
});

it('expone la estructura de card con fase mapeada (RF-EVA-01)', function () {
    $ctx = evaluadorAsignacionContext(['fase' => 'Final']);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones');

    $response->assertOk()->assertJsonStructure([
        'data' => [[
            'id',
            'proyecto' => ['id', 'codigo', 'titulo', 'estudiantes', 'director'],
            'fase',
            'evaluado',
            'created_at',
        ]],
    ]);
    expect($response->json('data.0.fase'))->toBe('presentacion_final');
    expect($response->json('data.0.proyecto.codigo'))->toBe($ctx['proyecto']->code);
    expect($response->json('data.0.proyecto.director.name'))->toBe($ctx['director']->name);
    expect($response->json('data.0.proyecto.estudiantes'))->toHaveCount(1);
    expect($response->json('data.0.evaluado'))->toBeFalse();
});

// =========================================================================
// RF-EVA-02 — Detalle de asignación con contexto completo
// =========================================================================

it('devuelve el detalle completo de una asignación pendiente (RF-EVA-02)', function () {
    $ctx = evaluadorAsignacionContext();
    $entrega = crearEntregaParaEvaluador($ctx);
    VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'archivos/doc-v1.pdf',
        'original_name' => 'doc-v1.pdf',
        'director_notes' => 'Revisar estructura',
        'uploaded_at' => '2026-08-01 10:00:00',
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/detalle");

    $response->assertOk();
    expect($response->json('fase'))->toBe('anteproyecto');
    expect($response->json('proyecto.codigo'))->toBe($ctx['proyecto']->code);
    expect($response->json('proyecto.estudiantes'))->toHaveCount(1);
    expect($response->json('entrega.id'))->toBe($entrega->id);
    expect($response->json('entrega.archivos_requeridos.0.slug'))->toBe('documento-proyecto');
    expect($response->json('entrega.due_date'))->toBe('2026-08-15');
    expect($response->json('entrega.versiones_documento'))->toHaveCount(1);
    expect($response->json('entrega.versiones_documento.0.file_path'))->toBe('archivos/doc-v1.pdf');
    expect($response->json('entrega.versiones_documento.0.director_notes'))->toBe('Revisar estructura');
    expect($response->json('evaluacion'))->toBeNull();
});

it('rechaza el detalle de una asignación de otro evaluador con 403 (RF-EVA-02)', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $ctx = evaluadorAsignacionContext([], $semestre);
    $otro = evaluadorAsignacionContext([], $semestre);
    $otro['asignacion']->update(['evaluado' => true]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$otro['asignacion']->id}/detalle");

    $response->assertStatus(403);
    expect($response->json('error.message'))->toBe('No tiene acceso a esta asignación');
});

it('incluye director_grade del proyecto cuando existe (RF-NOT-04 / D3-rev)', function () {
    $ctx = evaluadorAsignacionContext();
    $entrega = crearEntregaParaEvaluador($ctx);
    EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $ctx['proyecto']->id,
        'estado' => 'pendiente',
        'director_grade' => 4.0,
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/detalle");

    $response->assertOk();
    expect((float) $response->json('entrega.director_grade'))->toBe(4.0);
});

it('incluye director_grade como null cuando el proyecto no tiene nota (RF-NOT-04)', function () {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/detalle");

    $response->assertOk();
    expect($response->json('entrega.director_grade'))->toBeNull();
});

it('devuelve el director_grade del proyecto evaluado, no el de la entrega general (D3-rev)', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $ctxA = evaluadorAsignacionContext([], $semestre);
    $ctxB = evaluadorAsignacionContext([], $semestre);

    // Both projects share the SAME general delivery (same semester + phase).
    $entrega = crearEntregaParaEvaluador($ctxA);
    EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $ctxA['proyecto']->id,
        'director_grade' => 4.0,
    ]);
    EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $ctxB['proyecto']->id,
        'director_grade' => 3.5,
    ]);

    $respA = $this->actingAs($ctxA['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ctxA['asignacion']->id}/detalle");
    $respB = $this->actingAs($ctxB['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ctxB['asignacion']->id}/detalle");

    expect((float) $respA->json('entrega.director_grade'))->toBe(4.0);
    expect((float) $respB->json('entrega.director_grade'))->toBe(3.5);
});

it('incluye director_grade del proyecto en la card cuando existe (D3-rev)', function () {
    $ctx = evaluadorAsignacionContext();
    $entrega = crearEntregaParaEvaluador($ctx);
    EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $ctx['proyecto']->id,
        'director_grade' => 4.5,
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones');

    $response->assertOk();
    expect((float) $response->json('data.0.director_grade'))->toBe(4.5);
});

it('devuelve la evaluación enviada cuando la asignación ya fue evaluada (RF-EVA-02)', function () {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);
    $ctx['asignacion']->update(['evaluado' => true]);
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ctx['asignacion']->id,
        'nota' => 4.5,
        'observaciones' => 'Bien estructurado',
        'evaluated_at' => now(),
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/detalle");

    $response->assertOk();
    expect((float) $response->json('evaluacion.nota'))->toBe(4.5);
    expect($response->json('evaluacion.observaciones'))->toBe('Bien estructurado');
    expect($response->json('evaluacion.evaluated_at'))->not->toBeNull();
});

// =========================================================================
// RF-EVA-03 — Envío de nota + observaciones (inmutable)
// =========================================================================

it('envía la evaluación y marca la asignación como evaluada (RF-EVA-03)', function () {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);

    $response = $this->actingAs($ctx['evaluador'])
        ->postJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar", [
            'nota' => 4.5,
            'observaciones' => 'Documento bien estructurado',
        ]);

    $response->assertCreated();
    expect($ctx['asignacion']->fresh()->evaluado)->toBeTrue();

    $registro = EvaluacionEvaluador::where('evaluador_proyecto_id', $ctx['asignacion']->id)->first();
    expect($registro)->not->toBeNull();
    expect((float) $registro->nota)->toBe(4.5);
    expect($registro->observaciones)->toBe('Documento bien estructurado');
    expect($registro->evaluated_at)->not->toBeNull();
});

it('rechaza notas fuera del rango 0-5 con 422 (RF-EVA-03)', function (float $nota) {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);

    $response = $this->actingAs($ctx['evaluador'])
        ->postJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar", [
            'nota' => $nota,
            'observaciones' => 'Observación válida',
        ]);

    $response->assertStatus(422);
    expect($response->json('error.message'))->toBe('La nota debe estar entre 0 y 5');
})->with([5.01, 6.0, -0.5]);

it('rechaza notas con más de 2 decimales (D7, RF-EVA-03)', function () {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);

    $response = $this->actingAs($ctx['evaluador'])
        ->postJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar", [
            'nota' => 4.567,
            'observaciones' => 'Observación válida',
        ]);

    $response->assertStatus(422);
});

it('rechaza observaciones vacías con 422 (RF-EVA-03)', function () {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);

    $response = $this->actingAs($ctx['evaluador'])
        ->postJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar", [
            'nota' => 4.0,
            'observaciones' => '',
        ]);

    $response->assertStatus(422);
});

it('rechaza el re-envío con 409 cuando la asignación ya fue evaluada (D6, RF-EVA-03)', function () {
    $ctx = evaluadorAsignacionContext();
    crearEntregaParaEvaluador($ctx);
    $ctx['asignacion']->update(['evaluado' => true]);
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ctx['asignacion']->id,
        'nota' => 4.0,
        'observaciones' => 'Primera evaluación',
        'evaluated_at' => now(),
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->postJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar", [
            'nota' => 5.0,
            'observaciones' => 'Segunda evaluación',
        ]);

    $response->assertStatus(409);
    expect($response->json('error.message'))->toBe('La evaluación ya fue enviada y no puede modificarse');
});

it('rechaza evaluar una asignación de otro evaluador con 403 (RF-EVA-03)', function () {
    $semestre = Semestre::create(['name' => '2026-1', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30']);
    $ctx = evaluadorAsignacionContext([], $semestre);
    $otro = evaluadorAsignacionContext([], $semestre);

    $response = $this->actingAs($ctx['evaluador'])
        ->postJson("/api/evaluador/asignaciones/{$otro['asignacion']->id}/evaluar", [
            'nota' => 4.0,
            'observaciones' => 'No debería persistir',
        ]);

    $response->assertStatus(403);
    expect(EvaluacionEvaluador::count())->toBe(0);
});

it('no expone rutas de edición ni borrado de la evaluación (RF-EVA-03)', function () {
    $ctx = evaluadorAsignacionContext();

    $this->actingAs($ctx['evaluador'])
        ->putJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar")
        ->assertStatus(405);

    $this->actingAs($ctx['evaluador'])
        ->deleteJson("/api/evaluador/asignaciones/{$ctx['asignacion']->id}/evaluar")
        ->assertStatus(405);
});
