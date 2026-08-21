<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::factory()->create();
});

it('cambia el director del proyecto y devuelve la nueva asignación', function () {
    $actual = User::factory()->director()->create();
    $nuevo = User::factory()->director()->create();
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $actual->id,
        'title' => 'Título original',
    ]);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/proyectos/{$proyecto->id}", [
            'title' => 'Título original',
            'director_id' => $nuevo->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $proyecto->id)
        ->assertJsonPath('data.director_id', $nuevo->id)
        ->assertJsonPath('data.director.id', $nuevo->id);

    expect($proyecto->fresh()->director_id)->toBe($nuevo->id);
    expect(Proyecto::query()->where('id', $proyecto->id)->count())->toBe(1);
});

it('rechaza asignar como director a un usuario que no es Director', function () {
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $director->id,
    ]);

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/proyectos/{$proyecto->id}", [
            'director_id' => $estudiante->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['director_id']);

    expect($proyecto->fresh()->director_id)->toBe($director->id);
});

it('estudiante no puede actualizar un proyecto', function () {
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $otroDirector = User::factory()->director()->create();
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $director->id,
    ]);

    $this->actingAs($estudiante)
        ->putJson("/api/admin/proyectos/{$proyecto->id}", [
            'director_id' => $otroDirector->id,
        ])
        ->assertStatus(403);
});

it('sync de estudiantes no duplica filas ni deja al estudiante en dos proyectos', function () {
    $director = User::factory()->director()->create();
    $e1 = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $e2 = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $director->id,
    ]);
    $proyecto->estudiantes()->attach($e1->id);

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/proyectos/{$proyecto->id}", [
            'director_id' => $director->id,
            'student_ids' => [$e1->id, $e2->id],
        ])
        ->assertOk();

    expect($proyecto->fresh()->estudiantes()->pluck('users.id')->sort()->values()->all())
        ->toBe([$e1->id, $e2->id]);
});

it('filtra proyectos por grupo_id usando semester_id real', function () {
    $otroSemestre = Semestre::factory()->create(['name' => '2025-2']);
    $p1 = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'title' => 'Del grupo actual',
    ]);
    $p2 = Proyecto::factory()->create([
        'semester_id' => $otroSemestre->id,
        'title' => 'Del otro grupo',
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos?grupo_id='.$this->semestre->id);

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($p1->id)->not->toContain($p2->id);
});

it('filtra proyectos por semester_id como alias de grupo', function () {
    $otroSemestre = Semestre::factory()->create();
    $p1 = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
    Proyecto::factory()->create(['semester_id' => $otroSemestre->id]);

    $ids = collect(
        $this->actingAs($this->coordinador)
            ->getJson('/api/admin/proyectos?semester_id='.$this->semestre->id)
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($ids)->toContain($p1->id)->toHaveCount(1);
});
