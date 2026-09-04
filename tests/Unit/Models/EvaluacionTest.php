<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('Evaluacion model existe y extiende Model', function () {
    $evaluacion = new Evaluacion;
    expect($evaluacion)->toBeInstanceOf(Model::class);
});

test('Evaluacion tiene los fillable fields correctos', function () {
    $evaluacion = new Evaluacion;
    $expected = [
        'entrega_id', 'evaluador_id', 'criterio',
        'percentage', 'grade', 'comment', 'evaluated_at',
    ];

    foreach ($expected as $field) {
        expect(in_array($field, $evaluacion->getFillable(), true))
            ->toBeTrue("Evaluacion debería ser fillable para {$field}");
    }
});

test('Evaluacion casts percentage a decimal', function () {
    $evaluacion = Evaluacion::factory()->create(['percentage' => 75]);

    expect((float) $evaluacion->percentage)->toBe(75.0);
});

test('Evaluacion casts grade a decimal', function () {
    $evaluacion = Evaluacion::factory()->create(['grade' => 4.5]);

    expect((float) $evaluacion->grade)->toBe(4.5);
});

test('Evaluacion casts evaluated_at a datetime', function () {
    $evaluacion = Evaluacion::factory()->create(['evaluated_at' => now()]);

    expect($evaluacion->evaluated_at)->toBeInstanceOf(Carbon::class);
});

test('Evaluacion.entrega relation retorna BelongsTo', function () {
    $evaluacion = new Evaluacion;
    expect($evaluacion->entrega())->toBeInstanceOf(BelongsTo::class);
    expect($evaluacion->entrega()->getRelated())->toBeInstanceOf(Entrega::class);
});

test('Evaluacion.evaluador relation retorna BelongsTo', function () {
    $evaluacion = new Evaluacion;
    expect($evaluacion->evaluador())->toBeInstanceOf(BelongsTo::class);
    expect($evaluacion->evaluador()->getRelated())->toBeInstanceOf(User::class);
});

test('Evaluacion factory crea una evaluacion persistida', function () {
    $evaluacion = Evaluacion::factory()->create();

    expect($evaluacion->exists)->toBeTrue();
    expect($evaluacion->criterio)->not->toBeNull();
    expect($evaluacion->grade)->toBeGreaterThanOrEqual(0);
});

test('Evaluacion permite comment nulo', function () {
    $evaluacion = Evaluacion::factory()->create(['comment' => null]);

    expect($evaluacion->comment)->toBeNull();
});
