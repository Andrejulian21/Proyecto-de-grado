<?php

declare(strict_types=1);

use App\Models\EvaluacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{semestre: Semestre, director: User, proyecto: Proyecto, estudiante: User, evaluador: User, asignacion: EvaluadorProyecto}
 */
function areaEvaluadorContext(array $overrides = [], ?Semestre $semestre = null, ?User $evaluador = null): array
{
    $semestre ??= Semestre::factory()->create([
        'name' => 'S-'.substr(str_replace('.', '', uniqid('', true)), -10),
        'is_active' => true,
    ]);
    $director = User::factory()->director()->create();
    $proyecto = Proyecto::factory()->create([
        'title' => $overrides['title'] ?? 'Plataforma de tutoría UNAB',
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    unset($overrides['title']);

    $estudiante = User::factory()->create(['name' => $overrides['estudiante_nombre'] ?? 'Laura Jiménez']);
    unset($overrides['estudiante_nombre']);
    $proyecto->estudiantes()->attach($estudiante->id);

    $evaluador ??= User::factory()->external()->create([
        'name' => 'Eva Externa',
        'password_changed_at' => now(),
    ]);

    $asignacion = EvaluadorProyecto::create(array_merge([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'invitation_status' => 'Aceptada',
        'assigned_at' => now(),
        'fase' => 'Anteproyecto',
        'evaluado' => false,
        'fecha' => '2026-09-15',
        'hora_inicio' => '09:00',
        'hora_fin' => '10:00',
    ], $overrides));

    return compact('semestre', 'director', 'proyecto', 'estudiante', 'evaluador', 'asignacion');
}

it('el dashboard obtiene conteos reales del evaluador autenticado', function () {
    $ctx = areaEvaluadorContext();
    $otra = areaEvaluadorContext(['evaluado' => true, 'title' => 'Segundo proyecto'], $ctx['semestre'], $ctx['evaluador']);
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $otra['asignacion']->id,
        'nota' => 4.25,
        'observaciones' => 'Sustentación clara',
        'evaluated_at' => now(),
    ]);
    areaEvaluadorContext(['title' => 'Proyecto ajeno']);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/dashboard')
        ->assertOk();

    expect($response->json('data.evaluador.name'))->toBe($ctx['evaluador']->name)
        ->and($response->json('data.evaluador.email'))->toBe($ctx['evaluador']->email)
        ->and($response->json('data.resumen.asignadas'))->toBe(2)
        ->and($response->json('data.resumen.pendientes'))->toBe(1)
        ->and($response->json('data.resumen.realizadas'))->toBe(1)
        ->and($response->json('data.resumen.asignadas'))->not->toBe(6)
        ->and($response->json('data.resumen.pendientes'))->not->toBe(4);
});

it('el dashboard no inventa proximas sin fecha y lista las que si tienen', function () {
    $ctx = areaEvaluadorContext(['fecha' => null, 'hora_inicio' => null, 'hora_fin' => null]);
    $conFecha = areaEvaluadorContext([
        'title' => 'Con fecha',
        'fecha' => now()->addDays(3)->toDateString(),
        'hora_inicio' => '14:00',
    ], $ctx['semestre'], $ctx['evaluador']);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/dashboard')
        ->assertOk();

    $ids = collect($response->json('data.proximas'))->pluck('id')->all();
    expect($ids)->toContain($conFecha['asignacion']->id)
        ->and($ids)->not->toContain($ctx['asignacion']->id)
        ->and($response->json('data.resumen.sin_fecha'))->toBe(1);
});

it('las evaluaciones pendientes corresponden solo al evaluador autenticado', function () {
    $ctx = areaEvaluadorContext();
    areaEvaluadorContext(['evaluado' => true, 'title' => 'Ya evaluada'], $ctx['semestre'], $ctx['evaluador']);
    areaEvaluadorContext(['title' => 'De otro evaluador']);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones?estado=pendiente')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ctx['asignacion']->id)
        ->and($ids)->toHaveCount(1)
        ->and($response->json('data.0.estado'))->toBe('pendiente');
});

it('las evaluaciones completadas aparecen en el historial con nota de la BD', function () {
    $ctx = areaEvaluadorContext(['evaluado' => true]);
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ctx['asignacion']->id,
        'nota' => 3.5,
        'observaciones' => 'Aceptable',
        'evaluated_at' => '2026-08-20 10:00:00',
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones?estado=evaluada')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and((float) $response->json('data.0.nota'))->toBe(3.5)
        ->and($response->json('data.0.estado'))->toBe('evaluada');
});

it('la busqueda de pendientes filtra por titulo codigo o estudiante', function () {
    $ctx = areaEvaluadorContext(['title' => 'Sistema de inventario']);
    areaEvaluadorContext(['title' => 'Red inalámbrica campus'], $ctx['semestre'], $ctx['evaluador']);

    $porTitulo = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones?estado=pendiente&q=inventario')
        ->assertOk();
    expect(collect($porTitulo->json('data'))->pluck('id')->all())
        ->toContain($ctx['asignacion']->id)
        ->and(collect($porTitulo->json('data')))->toHaveCount(1);

    $porCodigo = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones?estado=pendiente&q='.urlencode($ctx['proyecto']->code))
        ->assertOk();
    expect(collect($porCodigo->json('data'))->pluck('id')->all())->toContain($ctx['asignacion']->id);

    $porEstudiante = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones?estado=pendiente&q=Laura')
        ->assertOk();
    expect(collect($porEstudiante->json('data'))->pluck('id')->all())->toContain($ctx['asignacion']->id);
});

it('la busqueda del historial respeta el evaluador autenticado', function () {
    $ctx = areaEvaluadorContext(['evaluado' => true, 'title' => 'Blockchain certificados']);
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ctx['asignacion']->id,
        'nota' => 4.0,
        'observaciones' => 'Ok',
        'evaluated_at' => now(),
    ]);
    $ajena = areaEvaluadorContext(['evaluado' => true, 'title' => 'Blockchain certificados otro']);
    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $ajena['asignacion']->id,
        'nota' => 5.0,
        'observaciones' => 'Ajena',
        'evaluated_at' => now(),
    ]);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/mis-asignaciones?estado=evaluada&q=Blockchain')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ctx['asignacion']->id)
        ->and($ids)->not->toContain($ajena['asignacion']->id);
});

it('el calendario muestra fechas reales y omite las nulas', function () {
    $ctx = areaEvaluadorContext(['fecha' => '2026-10-01', 'hora_inicio' => '08:30']);
    areaEvaluadorContext([
        'title' => 'Sin fecha',
        'fecha' => null,
        'hora_inicio' => null,
    ], $ctx['semestre'], $ctx['evaluador']);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/calendario')
        ->assertOk();

    $fechas = collect($response->json('data'))->pluck('fecha')->all();
    expect($fechas)->toContain('2026-10-01')
        ->and($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.hora_inicio'))->toBe('08:30')
        ->and($response->json('data.0.proyecto.codigo'))->toBe($ctx['proyecto']->code);
});

it('el calendario no muestra evaluaciones de otros evaluadores', function () {
    $ctx = areaEvaluadorContext(['fecha' => '2026-11-02']);
    $ajena = areaEvaluadorContext(['fecha' => '2026-11-02', 'title' => 'Ajena']);

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/evaluador/calendario')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ctx['asignacion']->id)
        ->and($ids)->not->toContain($ajena['asignacion']->id);
});

it('un evaluador no consulta el detalle de una asignacion ajena', function () {
    $ctx = areaEvaluadorContext();
    $ajena = areaEvaluadorContext(['title' => 'Otro']);

    $this->actingAs($ctx['evaluador'])
        ->getJson("/api/evaluador/asignaciones/{$ajena['asignacion']->id}/detalle")
        ->assertForbidden();
});

it('la seccion de notas respeta el ambito del evaluador', function () {
    $ctx = areaEvaluadorContext();
    $ajena = areaEvaluadorContext(['title' => 'Fuera de ambito']);

    $this->actingAs($ctx['evaluador'])
        ->getJson('/api/notas?proyecto_id='.$ajena['proyecto']->id)
        ->assertForbidden();

    $propia = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/notas')
        ->assertOk();
    $ids = collect($propia->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($ctx['proyecto']->id)
        ->and($ids)->not->toContain($ajena['proyecto']->id);
});

it('el modulo funciona sin pendientes y sin historial', function () {
    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);

    $this->actingAs($evaluador)
        ->getJson('/api/evaluador/mis-asignaciones?estado=pendiente')
        ->assertOk()
        ->assertJson(['data' => []]);

    $this->actingAs($evaluador)
        ->getJson('/api/evaluador/mis-asignaciones?estado=evaluada')
        ->assertOk()
        ->assertJson(['data' => []]);

    $dash = $this->actingAs($evaluador)
        ->getJson('/api/evaluador/dashboard')
        ->assertOk();
    expect($dash->json('data.resumen.pendientes'))->toBe(0)
        ->and($dash->json('data.resumen.realizadas'))->toBe(0)
        ->and($dash->json('data.proximas'))->toBe([]);
});

it('el sidebar del evaluador no incluye opciones obsoletas', function () {
    $src = file_get_contents(resource_path('js/components/layout/Sidebar.tsx'));
    expect($src)->not->toBeFalse();
    preg_match('/EvaluadorExterno:\s*\[(.*?)\]/s', (string) $src, $matches);
    $block = $matches[1] ?? '';

    expect($block)->toContain('/dashboard/evaluador-externo')
        ->and($block)->toContain('/evaluador/calendario')
        ->and($block)->toContain('/evaluador/pendientes')
        ->and($block)->toContain('/evaluador/historial')
        ->and($block)->toContain('/notas')
        ->and($block)->not->toContain('/anuncios')
        ->and($block)->not->toContain('/recursos')
        ->and($block)->not->toContain('Mis Asignaciones');
});

it('el dashboard no depende de mocks quemados', function () {
    $src = file_get_contents(resource_path('js/pages/dashboard/EvaluadorDashboard.tsx'));
    $hook = file_get_contents(resource_path('js/hooks/useEvaluadorAsignaciones.ts'));
    expect($src)->not->toBeFalse()
        ->and($src)->not->toContain('MOCK_EVALUATIONS')
        ->and($src)->not->toContain('PG-2403')
        ->and($src)->not->toContain('value={6}')
        ->and($hook)->toContain('/api/evaluador/dashboard');
});
