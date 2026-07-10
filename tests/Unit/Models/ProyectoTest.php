<?php

declare(strict_types=1);

use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Proyecto model existe y extiende Model', function () {
    $proyecto = new Proyecto;
    expect($proyecto)->toBeInstanceOf(Illuminate\Database\Eloquent\Model::class);
});

test('Proyecto tiene los fillable fields correctos', function () {
    $proyecto = new Proyecto;
    $expected = [
        'title', 'director_id', 'semester_id',
        'current_phase', 'status', 'requires_group_justification',
    ];

    foreach ($expected as $field) {
        expect(in_array($field, $proyecto->getFillable(), true))
            ->toBeTrue("Proyecto debería ser fillable para {$field}");
    }
});

test('Proyecto casts status a EstadoProyecto enum', function () {
    $semestre = Semestre::factory()->create();
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'status' => 'en_curso',
    ]);

    expect($proyecto->status)->toBeInstanceOf(EstadoProyecto::class);
    expect($proyecto->status)->toBe(EstadoProyecto::EnCurso);
});

test('Proyecto casts current_phase a FaseProyecto enum', function () {
    $proyecto = Proyecto::factory()->create(['current_phase' => 'desarrollo']);

    expect($proyecto->current_phase)->toBeInstanceOf(FaseProyecto::class);
    expect($proyecto->current_phase)->toBe(FaseProyecto::Desarrollo);
});

test('Proyecto casts requires_group_justification a booleano', function () {
    $proyecto = Proyecto::factory()->create(['requires_group_justification' => 1]);

    expect($proyecto->requires_group_justification)->toBeTrue();
    expect($proyecto->requires_group_justification)->toBeBool();
});

test('Proyecto.semestre relation retorna BelongsTo', function () {
    $proyecto = new Proyecto;
    expect($proyecto->semestre())->toBeInstanceOf(BelongsTo::class);
});

test('Proyecto.director relation retorna BelongsTo', function () {
    $proyecto = new Proyecto;
    expect($proyecto->director())->toBeInstanceOf(BelongsTo::class);
});

test('Proyecto.estudiantes relation retorna BelongsToMany', function () {
    $proyecto = new Proyecto;
    expect($proyecto->estudiantes())->toBeInstanceOf(BelongsToMany::class);
});

test('Proyecto.entregas relation retorna HasMany de Entrega', function () {
    $proyecto = new Proyecto;
    expect($proyecto->entregas())->toBeInstanceOf(HasMany::class);
    expect($proyecto->entregas()->getRelated())->toBeInstanceOf(Entrega::class);
});

test('Proyecto.bitacoras relation retorna HasMany de Bitacora', function () {
    $proyecto = new Proyecto;
    expect($proyecto->bitacoras())->toBeInstanceOf(HasMany::class);
    expect($proyecto->bitacoras()->getRelated())->toBeInstanceOf(Bitacora::class);
});

test('Proyecto factory crea un proyecto persistido', function () {
    $proyecto = Proyecto::factory()->create();

    expect($proyecto->exists)->toBeTrue();
    expect($proyecto->title)->not->toBeNull();
    expect($proyecto->code)->not->toBeNull();
});

test('Proyecto scopeEnSemestresActivos solo incluye proyectos en semestres activos', function () {
    $activo = Semestre::factory()->create(['is_active' => true, 'name' => '2026-1']);
    $inactivo = Semestre::factory()->create(['is_active' => false, 'name' => '2025-2']);

    $proyectoActivo = Proyecto::factory()->create(['semester_id' => $activo->id]);
    $proyectoInactivo = Proyecto::factory()->create(['semester_id' => $inactivo->id]);

    $resultados = Proyecto::enSemestresActivos()->get();

    expect($resultados->pluck('id')->toArray())->toContain($proyectoActivo->id);
    expect($resultados->pluck('id')->toArray())->not->toContain($proyectoInactivo->id);
});

test('Proyecto auto-genera codigo con formato PG-YYYYNNN al crear', function () {
    $semestre = Semestre::factory()->create(['name' => '2026-1']);
    $proyecto = Proyecto::factory()->create(['semester_id' => $semestre->id]);

    expect($proyecto->code)->toMatch('/^PG-20261\d{3}$/');
});

test('Proyecto permite director nulo', function () {
    $proyecto = Proyecto::factory()->create(['director_id' => null]);

    expect($proyecto->director_id)->toBeNull();
});

test('Proyecto puede tener estudiantes asociados', function () {
    $proyecto = Proyecto::factory()->create();
    $estudiante = User::factory()->create();

    $proyecto->estudiantes()->attach($estudiante);

    expect($proyecto->estudiantes)->toHaveCount(1);
    expect($proyecto->estudiantes->first()->id)->toBe($estudiante->id);
});
