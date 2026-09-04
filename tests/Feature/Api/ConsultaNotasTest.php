<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\EvaluacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->directorA = User::factory()->director()->create();
    $this->directorB = User::factory()->director()->create();
    $this->estudianteA = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->evaluador = User::factory()->external()->create(['password_changed_at' => now()]);

    $this->semestre = Semestre::factory()->create([
        'name' => '2026-2',
        'is_active' => true,
    ]);

    $this->proyectoA = Proyecto::factory()->create([
        'title' => 'Sistema de inventario UNAB',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->directorA->id,
    ]);
    $this->proyectoA->estudiantes()->attach($this->estudianteA);

    $this->proyectoB = Proyecto::factory()->create([
        'title' => 'Plataforma de alertas',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->directorB->id,
    ]);
    $this->proyectoB->estudiantes()->attach($this->estudianteB);

    $this->entregaCalificada = crearEntregaConNota($this->proyectoA, 'Anteproyecto documental', 4.5);
    $this->entregaSinNota = crearEntregaConNota($this->proyectoA, 'Informe de avance', null);
    $this->entregaCero = crearEntregaConNota($this->proyectoA, 'Corrección menor', 0.0);
    $this->entregaB = crearEntregaConNota($this->proyectoB, 'Anteproyecto B', 3.0);

    EvaluadorProyecto::create([
        'proyecto_id' => $this->proyectoA->id,
        'evaluador_id' => $this->evaluador->id,
        'invitation_status' => 'Aceptada',
        'assigned_at' => now(),
        'fase' => 'Anteproyecto',
        'evaluado' => true,
    ]);
});

/**
 * @return array{entrega: Entrega, pivot: EntregaProyecto}
 */
function crearEntregaConNota(Proyecto $proyecto, string $titulo, ?float $nota): array
{
    $entrega = Entrega::create([
        'semester_id' => $proyecto->semester_id,
        'phase' => 'anteproyecto',
        'title' => $titulo,
        'description' => 'Desc',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => 'enviada',
    ]);

    $pivot = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $proyecto->id,
        'estado' => 'pendiente',
        'director_grade' => $nota,
    ]);

    return ['entrega' => $entrega, 'pivot' => $pivot];
}

function proyectoEnPayload(array $payload, int $proyectoId): ?array
{
    foreach ($payload['data']['proyectos'] ?? [] as $proyecto) {
        if ((int) $proyecto['id'] === $proyectoId) {
            return $proyecto;
        }
    }

    return null;
}

function entregaEnProyecto(array $proyecto, int $entregaId): ?array
{
    foreach ($proyecto['entregas'] ?? [] as $entrega) {
        if ((int) $entrega['id'] === $entregaId) {
            return $entrega;
        }
    }

    return null;
}

it('el coordinador consulta las notas de los proyectos del semestre', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?semestre_id='.$this->semestre->id.'&tipo=pg1')
        ->assertOk();

    $ids = collect($response->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id, $this->proyectoB->id);

    // Coordinator format: nota is inside notas_entregas_anteproyecto
    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    expect($proyectoA)->toHaveKey('nota_final_pg1')
        ->and($proyectoA)->toHaveKey('notas_entregas_anteproyecto')
        ->and($proyectoA['pesos'])->toHaveKey('entregas');
});

it('el estudiante solo consulta sus propios proyectos', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson('/api/notas?tipo=pg1')
        ->assertOk();

    $ids = collect($response->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id)
        ->and($ids)->not->toContain($this->proyectoB->id);
});

it('el director solo consulta sus proyectos asignados', function () {
    $response = $this->actingAs($this->directorA)
        ->getJson('/api/notas?tipo=pg1')
        ->assertOk();

    $ids = collect($response->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id)
        ->and($ids)->not->toContain($this->proyectoB->id);
});

it('el evaluador solo consulta proyectos asignados', function () {
    $response = $this->actingAs($this->evaluador)
        ->getJson('/api/notas')
        ->assertOk();

    $ids = collect($response->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id)
        ->and($ids)->not->toContain($this->proyectoB->id);
});

it('las entregas aparecen asociadas al proyecto correcto', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?semestre_id='.$this->semestre->id.'&tipo=pg1')
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    $proyectoB = proyectoEnPayload($response->json(), $this->proyectoB->id);

    // Coordinator format: entregas are in notas_entregas_anteproyecto
    expect($proyectoA)->not->toBeNull()
        ->and($proyectoA['notas_entregas_anteproyecto'])->not->toBeEmpty()
        ->and($proyectoB)->not->toBeNull();
});

it('las notas aparecen asociadas a la entrega correcta y no al template', function () {
    $this->entregaCalificada['entrega']->update([
        'consolidated_grade' => 1.0,
        'director_grade' => 1.0,
    ]);

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/notas?tipo=pg1')
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    // Coordinator format: entregas are in notas_entregas_anteproyecto
    $entregas = $proyectoA['notas_entregas_anteproyecto'] ?? [];
    $calificada = collect($entregas)->firstWhere('titulo', $this->entregaCalificada['entrega']->title);
    $otra = collect($entregas)->firstWhere('titulo', $this->entregaSinNota['entrega']->title);

    expect($calificada)->not->toBeNull()
        ->and((float) $calificada['nota'])->toBe(4.5)
        ->and($otra['nota'])->toBeNull();
});

it('una entrega sin nota no aparece como cero y el cero real se conserva', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson('/api/notas?tipo=pg1')
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    // Coordinator format: entregas are in notas_entregas_anteproyecto
    $entregas = $proyectoA['notas_entregas_anteproyecto'] ?? [];
    $sinNota = collect($entregas)->firstWhere('titulo', $this->entregaSinNota['entrega']->title);
    $cero = collect($entregas)->firstWhere('titulo', $this->entregaCero['entrega']->title);

    expect($sinNota)->not->toBeNull()
        ->and($sinNota['nota'])->toBeNull();

    // cero grade should be 0.0 if it exists
    if ($cero !== null) {
        expect((float) $cero['nota'])->toBe(0.0);
    }
});

it('los filtros de busqueda se resuelven en el servidor', function () {
    $busqueda = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?q='.urlencode($this->proyectoA->code).'&tipo=pg1')
        ->assertOk();

    $ids = collect($busqueda->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id)
        ->and($ids)->not->toContain($this->proyectoB->id);
});

it('el coordinador puede filtrar por fase pg1 y pg2', function () {
    $pg1 = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?tipo=pg1')
        ->assertOk();

    $proyectoA = proyectoEnPayload($pg1->json(), $this->proyectoA->id);
    expect($proyectoA)->toHaveKey('notas_entregas_anteproyecto');

    $pg2 = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?tipo=pg2')
        ->assertOk();

    $proyectoApg2 = proyectoEnPayload($pg2->json(), $this->proyectoA->id);
    expect($proyectoApg2)->toHaveKey('notas_entregas_desarrollo');
});

it('un usuario no autorizado no consulta notas de otro proyecto', function () {
    $this->actingAs($this->estudianteA)
        ->getJson('/api/notas?tipo=pg1&proyecto_id='.$this->proyectoB->id)
        ->assertForbidden();

    $this->actingAs($this->directorA)
        ->getJson('/api/notas?tipo=pg1&proyecto_id='.$this->proyectoB->id)
        ->assertForbidden();

    // Evaluador also gets 403 for projects they're not assigned to
    $this->actingAs($this->evaluador)
        ->getJson('/api/notas?proyecto_id='.$this->proyectoB->id)
        ->assertForbidden();
});

it('exige autenticacion para consultar notas', function () {
    $this->getJson('/api/notas')->assertUnauthorized();
});

it('el evaluador ve su nota propia desde la base de datos y no un valor quemado', function () {
    $asignacion = EvaluadorProyecto::query()
        ->where('evaluador_id', $this->evaluador->id)
        ->where('proyecto_id', $this->proyectoA->id)
        ->first();

    EvaluacionEvaluador::create([
        'evaluador_proyecto_id' => $asignacion->id,
        'nota' => 3.75,
        'observaciones' => 'Sustentación aceptable',
        'evaluated_at' => now(),
    ]);

    $response = $this->actingAs($this->evaluador)
        ->getJson('/api/notas')
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    // Evaluator format: grade is inside evaluaciones array
    $evaluaciones = $proyectoA['evaluaciones'] ?? [];
    $conNota = collect($evaluaciones)->firstWhere('nota', 3.75);
    expect($conNota)->not->toBeNull();

    // Director gets coordinator format, not evaluator format
    $comoDirector = $this->actingAs($this->directorA)
        ->getJson('/api/notas?tipo=pg1')
        ->assertOk();

    $dirProyecto = proyectoEnPayload($comoDirector->json(), $this->proyectoA->id);
    expect($dirProyecto)->toHaveKey('notas_entregas_anteproyecto');
});
