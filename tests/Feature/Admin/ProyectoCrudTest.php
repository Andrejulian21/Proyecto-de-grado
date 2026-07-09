<?php

declare(strict_types=1);

use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Enums\UserRole;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
});

it('coordinador puede listar proyectos', function () {
    \App\Models\Proyecto::create([
        'title' => 'Proyecto Alpha',
        'semester_id' => $this->semestre->id,
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'code', 'title', 'current_phase', 'status']],
        ]);
    expect($response->json('data'))->toHaveCount(1);
});

it('coordinador puede crear proyecto con título, semestre y director opcional', function () {
    $director = User::factory()->director()->create();
    $payload = [
        'title' => 'Sistema de Gestión',
        'semester_id' => $this->semestre->id,
        'director_id' => $director->id,
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', $payload);

    $response->assertCreated()
        ->assertJson(['data' => ['title' => 'Sistema de Gestión']]);
    expect(\App\Models\Proyecto::count())->toBe(1);
});

it('proyecto creado tiene código auto-generado (PG-20261001)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'IA para cultivos',
            'semester_id' => $this->semestre->id,
        ]);

    $response->assertCreated();
    $code = $response->json('data.code');
    expect($code)->toMatch('/^PG-20261\d{3}$/');
});

it('asociar 1-3 estudiantes al proyecto', function () {
    $estudiantes = User::factory()->count(2)->create(['role' => UserRole::Estudiante->value]);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Proyecto con estudiantes',
            'semester_id' => $this->semestre->id,
            'student_ids' => $estudiantes->pluck('id')->toArray(),
        ]);

    $response->assertCreated();
    $proyecto = \App\Models\Proyecto::find($response->json('data.id'));
    expect($proyecto->estudiantes)->toHaveCount(2);
});

it('3 estudiantes requiere requires_group_justification=true', function () {
    $estudiantes = User::factory()->count(3)->create(['role' => UserRole::Estudiante->value]);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Proyecto grupal',
            'semester_id' => $this->semestre->id,
            'student_ids' => $estudiantes->pluck('id')->toArray(),
        ]);

    $response->assertCreated();
    $proyecto = \App\Models\Proyecto::find($response->json('data.id'));
    expect($proyecto->requires_group_justification)->toBeTrue();
});

it('estudiante NO puede crear proyecto (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Hack',
            'semester_id' => $this->semestre->id,
        ]);

    $response->assertStatus(403);
});

it('fase y estado son los enums correctos', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Verificación enums',
            'semester_id' => $this->semestre->id,
        ]);

    $response->assertCreated();
    $proyecto = \App\Models\Proyecto::find($response->json('data.id'));
    expect($proyecto->current_phase)->toBe(FaseProyecto::Anteproyecto);
    expect($proyecto->current_phase->value)->toBe('anteproyecto');
    expect($proyecto->status)->toBe(EstadoProyecto::EnCurso);
    expect($proyecto->status->value)->toBe('en_curso');
});
