<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->otroProyecto = Proyecto::create([
        'title' => 'Otro Proyecto',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    // Crear entregas aprobadas (production shape: semester_id + pivot)
    $finalA = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Final A',
        'due_date' => '2026-03-01',
        'status' => 'aprobada',
    ]);
    $finalA->proyectos()->attach($this->proyecto->id);

    $finalB = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Final B',
        'due_date' => '2026-04-01',
        'status' => 'aprobada',
    ]);
    $finalB->proyectos()->attach($this->otroProyecto->id);

    // Crear entrega no aprobada (no debe aparecer)
    $noAprobada = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'No aprobada',
        'due_date' => '2026-03-01',
        'status' => 'pendiente',
    ]);
    $noAprobada->proyectos()->attach($this->proyecto->id);
});

// -- Acceso coordinador -------------------------------------------------------

it('coordinador puede ver entregas aprobadas', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

// -- Acceso denegado ----------------------------------------------------------

it('estudiante NO puede acceder a entregas finales (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/admin/entregas/finales');

    $response->assertStatus(403);
});

it('director NO puede acceder a entregas finales (403)', function () {
    $response = $this->actingAs($this->director)
        ->getJson('/api/admin/entregas/finales');

    $response->assertStatus(403);
});

// -- Filtros ------------------------------------------------------------------

it('filtra por proyecto_id', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales?proyecto_id='.$this->proyecto->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data')[0]['title'])->toBe('Final A');
});

it('filtra por fecha_desde', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales?fecha_desde=2026-03-15');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data')[0]['title'])->toBe('Final B');
});

it('filtra por fecha_hasta', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales?fecha_hasta=2026-03-31');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data')[0]['title'])->toBe('Final A');
});

it('filtra por director_id', function () {
    $otroDirector = User::factory()->director()->create();
    $proyectoOtroDirector = Proyecto::create([
        'title' => 'Proyecto Otro Director',
        'semester_id' => $this->semestre->id,
        'director_id' => $otroDirector->id,
    ]);
    $otra = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Final Otro Director',
        'due_date' => '2026-05-01',
        'status' => 'aprobada',
    ]);
    $otra->proyectos()->attach($proyectoOtroDirector->id);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales?director_id='.$this->director->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

// -- Paginación ---------------------------------------------------------------

it('resultados paginados', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/entregas/finales?per_page=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('total'))->toBe(2);
    expect($response->json('per_page'))->toBe(1);
    expect($response->json('current_page'))->toBe(1);
});
