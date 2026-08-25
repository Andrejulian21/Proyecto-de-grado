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
        ->getJson('/api/notas?semestre_id='.$this->semestre->id)
        ->assertOk();

    $ids = collect($response->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id, $this->proyectoB->id);

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    $calificada = entregaEnProyecto($proyectoA, $this->entregaCalificada['entrega']->id);
    $sinNota = entregaEnProyecto($proyectoA, $this->entregaSinNota['entrega']->id);

    expect($calificada['nota'])->toBe(4.5)
        ->and($calificada['estado_nota'])->toBe('calificada')
        ->and($sinNota['nota'])->toBeNull()
        ->and($sinNota['estado_nota'])->toBe('sin_calificar');
});

it('el estudiante solo consulta sus propios proyectos', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson('/api/notas')
        ->assertOk();

    $ids = collect($response->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id)
        ->and($ids)->not->toContain($this->proyectoB->id);
});

it('el director solo consulta sus proyectos asignados', function () {
    $response = $this->actingAs($this->directorA)
        ->getJson('/api/notas')
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
        ->getJson('/api/notas?semestre_id='.$this->semestre->id)
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    $proyectoB = proyectoEnPayload($response->json(), $this->proyectoB->id);

    expect(entregaEnProyecto($proyectoA, $this->entregaCalificada['entrega']->id))->not->toBeNull()
        ->and(entregaEnProyecto($proyectoA, $this->entregaB['entrega']->id))->toBeNull()
        ->and(entregaEnProyecto($proyectoB, $this->entregaB['entrega']->id))->not->toBeNull()
        ->and(entregaEnProyecto($proyectoB, $this->entregaCalificada['entrega']->id))->toBeNull();
});

it('las notas aparecen asociadas a la entrega correcta y no al template', function () {
    $this->entregaCalificada['entrega']->update([
        'consolidated_grade' => 1.0,
        'director_grade' => 1.0,
    ]);

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/notas')
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    $calificada = entregaEnProyecto($proyectoA, $this->entregaCalificada['entrega']->id);
    $otra = entregaEnProyecto($proyectoA, $this->entregaSinNota['entrega']->id);

    expect((float) $calificada['nota'])->toBe(4.5)
        ->and($otra['nota'])->toBeNull();
});

it('una entrega sin nota no aparece como cero y el cero real se conserva', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson('/api/notas')
        ->assertOk();

    $proyectoA = proyectoEnPayload($response->json(), $this->proyectoA->id);
    $sinNota = entregaEnProyecto($proyectoA, $this->entregaSinNota['entrega']->id);
    $cero = entregaEnProyecto($proyectoA, $this->entregaCero['entrega']->id);

    expect($sinNota['nota'])->toBeNull()
        ->and($sinNota['estado_nota'])->toBe('sin_calificar')
        ->and((float) $cero['nota'])->toBe(0.0)
        ->and($cero['estado_nota'])->toBe('calificada');
});

it('los filtros de estado y busqueda se resuelven en el servidor', function () {
    $sinCalificar = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?semestre_id='.$this->semestre->id.'&estado_nota=sin_calificar')
        ->assertOk();

    $proyectoFiltrado = proyectoEnPayload($sinCalificar->json(), $this->proyectoA->id);
    $idsSinNota = collect($proyectoFiltrado['entregas'])->pluck('id')->all();
    expect($idsSinNota)->toContain($this->entregaSinNota['entrega']->id)
        ->and($idsSinNota)->not->toContain($this->entregaCalificada['entrega']->id);

    $busqueda = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?q='.urlencode($this->proyectoA->code))
        ->assertOk();

    $ids = collect($busqueda->json('data.proyectos'))->pluck('id')->all();
    expect($ids)->toContain($this->proyectoA->id)
        ->and($ids)->not->toContain($this->proyectoB->id);

    $porEntrega = $this->actingAs($this->coordinador)
        ->getJson('/api/notas?entrega_id='.$this->entregaCalificada['entrega']->id)
        ->assertOk();

    $soloEsa = proyectoEnPayload($porEntrega->json(), $this->proyectoA->id);
    expect($soloEsa['entregas'])->toHaveCount(1)
        ->and($soloEsa['entregas'][0]['id'])->toBe($this->entregaCalificada['entrega']->id);
});

it('un usuario no autorizado no consulta notas de otro proyecto', function () {
    $this->actingAs($this->estudianteA)
        ->getJson('/api/notas?proyecto_id='.$this->proyectoB->id)
        ->assertForbidden();

    $this->actingAs($this->directorA)
        ->getJson('/api/notas?proyecto_id='.$this->proyectoB->id)
        ->assertForbidden();

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
    expect((float) $proyectoA['nota_evaluador'])->toBe(3.75);

    $comoDirector = $this->actingAs($this->directorA)
        ->getJson('/api/notas')
        ->assertOk();

    expect(proyectoEnPayload($comoDirector->json(), $this->proyectoA->id)['nota_evaluador'] ?? null)->toBeNull();
});
