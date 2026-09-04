<?php

declare(strict_types=1);

use App\Models\Proyecto;
use App\Models\Semestre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('Semestre model existe y extiende Model', function () {
    $semestre = new Semestre;
    expect($semestre)->toBeInstanceOf(Model::class);
});

test('Semestre tiene los fillable fields correctos', function () {
    $semestre = new Semestre;
    $expected = ['name', 'start_date', 'end_date', 'is_active'];

    foreach ($expected as $field) {
        expect(in_array($field, $semestre->getFillable(), true))
            ->toBeTrue("Semestre debería ser fillable para {$field}");
    }
});

test('Semestre casts start_date y end_date a fechas', function () {
    $semestre = Semestre::factory()->create([
        'start_date' => '2026-01-15',
        'end_date' => '2026-06-30',
    ]);

    expect($semestre->start_date)->toBeInstanceOf(Carbon::class);
    expect($semestre->end_date)->toBeInstanceOf(Carbon::class);
});

test('Semestre casts is_active a booleano', function () {
    $semestre = Semestre::factory()->create(['is_active' => 1]);

    expect($semestre->is_active)->toBeTrue();
    expect($semestre->is_active)->toBeBool();
});

test('Semestre.proyectos relation retorna HasMany de Proyecto', function () {
    $semestre = new Semestre;
    expect($semestre->proyectos())->toBeInstanceOf(HasMany::class);
    expect($semestre->proyectos()->getRelated())->toBeInstanceOf(Proyecto::class);
});

test('Semestre factory crea un semestre persistido', function () {
    $semestre = Semestre::factory()->create();

    expect($semestre->exists)->toBeTrue();
    expect($semestre->name)->toMatch('/^\d{4}-[12]$/');
});

test('Semestre scopeActivos solo incluye semestres activos', function () {
    Semestre::factory()->create(['is_active' => true]);
    Semestre::factory()->create(['is_active' => false]);

    $activos = Semestre::activos()->get();

    expect($activos)->toHaveCount(1);
    expect($activos->first()->is_active)->toBeTrue();
});

test('Semestre puede tener cero proyectos', function () {
    $semestre = Semestre::factory()->create();

    expect($semestre->proyectos)->toHaveCount(0);
});

test('Semestre puede tener multiples proyectos', function () {
    $semestre = Semestre::factory()->create();
    Proyecto::factory()->count(3)->create(['semester_id' => $semestre->id]);

    expect($semestre->proyectos)->toHaveCount(3);
});
