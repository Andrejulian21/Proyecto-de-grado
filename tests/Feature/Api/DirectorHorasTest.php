<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
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
    ]);

    $this->director = User::factory()->director()->create();
    $this->otroDirector = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $this->proyecto->estudiantes()->attach($this->estudiante->id);
});

test('director ve total horas acumuladas de su proyecto', function () {
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'A', 'meeting_date' => '2026-04-01', 'duration_hours' => 1.5]);
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'B', 'meeting_date' => '2026-04-02', 'duration_hours' => 2.0]);
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'C', 'meeting_date' => '2026-04-03', 'duration_hours' => 0.5]);

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/proyectos/{$this->proyecto->id}/horas");

    $response->assertOk()
        ->assertJsonStructure(['total_horas', 'total_bitacoras', 'proyecto_id']);
    expect($response->json('total_horas'))->toEqual(4.0);
    expect($response->json('total_bitacoras'))->toEqual(3);
});

test('director no puede ver horas de proyecto ajeno', function () {
    $response = $this->actingAs($this->otroDirector)
        ->getJson("/api/director/proyectos/{$this->proyecto->id}/horas");

    $response->assertStatus(403);
});

test('estudiante no puede acceder al endpoint de horas', function () {
    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/director/proyectos/{$this->proyecto->id}/horas");

    $response->assertStatus(403);
});

test('horas devuelve 0 cuando no hay bitacoras', function () {
    $response = $this->actingAs($this->director)
        ->getJson("/api/director/proyectos/{$this->proyecto->id}/horas");

    $response->assertOk();
    expect($response->json('total_horas'))->toEqual(0);
    expect($response->json('total_bitacoras'))->toEqual(0);
});

test('coordinador puede ver horas de cualquier proyecto', function () {
    $coordinador = User::factory()->coordinador()->create();

    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'A', 'meeting_date' => '2026-04-01', 'duration_hours' => 3.0]);

    $response = $this->actingAs($coordinador)
        ->getJson("/api/director/proyectos/{$this->proyecto->id}/horas");

    $response->assertOk();
    expect($response->json('total_horas'))->toEqual(3.0);
});
