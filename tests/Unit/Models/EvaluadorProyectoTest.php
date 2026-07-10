<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('EvaluadorProyecto model existe y extiende Model', function () {
    $ep = new EvaluadorProyecto;
    expect($ep)->toBeInstanceOf(Illuminate\Database\Eloquent\Model::class);
});

test('EvaluadorProyecto tiene los fillable fields correctos', function () {
    $ep = new EvaluadorProyecto;
    $expected = ['proyecto_id', 'evaluador_id', 'invitation_status', 'assigned_at'];

    foreach ($expected as $field) {
        expect(in_array($field, $ep->getFillable(), true))
            ->toBeTrue("EvaluadorProyecto debería ser fillable para {$field}");
    }
});

test('EvaluadorProyecto casts invitation_status a EstadoInvitacionEvaluador enum', function () {
    $ep = EvaluadorProyecto::factory()->create(['invitation_status' => 'Pendiente']);

    expect($ep->invitation_status)->toBeInstanceOf(EstadoInvitacionEvaluador::class);
    expect($ep->invitation_status)->toBe(EstadoInvitacionEvaluador::Pendiente);
});

test('EvaluadorProyecto casts assigned_at a datetime', function () {
    $ep = EvaluadorProyecto::factory()->create(['assigned_at' => now()]);

    expect($ep->assigned_at)->toBeInstanceOf(Carbon::class);
});

test('EvaluadorProyecto.proyecto relation retorna BelongsTo', function () {
    $ep = new EvaluadorProyecto;
    expect($ep->proyecto())->toBeInstanceOf(BelongsTo::class);
    expect($ep->proyecto()->getRelated())->toBeInstanceOf(Proyecto::class);
});

test('EvaluadorProyecto.evaluador relation retorna BelongsTo', function () {
    $ep = new EvaluadorProyecto;
    expect($ep->evaluador())->toBeInstanceOf(BelongsTo::class);
    expect($ep->evaluador()->getRelated())->toBeInstanceOf(User::class);
});

test('EvaluadorProyecto factory crea una asignacion persistida', function () {
    $ep = EvaluadorProyecto::factory()->create();

    expect($ep->exists)->toBeTrue();
    expect($ep->invitation_status)->not->toBeNull();
});

test('EvaluadorProyecto soporta estado Pendiente', function () {
    $ep = EvaluadorProyecto::factory()->create(['invitation_status' => 'Pendiente']);
    expect($ep->invitation_status)->toBe(EstadoInvitacionEvaluador::Pendiente);
});

test('EvaluadorProyecto soporta estado Aceptada', function () {
    $ep = EvaluadorProyecto::factory()->create(['invitation_status' => 'Aceptada']);
    expect($ep->invitation_status)->toBe(EstadoInvitacionEvaluador::Aceptada);
});

test('EvaluadorProyecto soporta estado Rechazada', function () {
    $ep = EvaluadorProyecto::factory()->create(['invitation_status' => 'Rechazada']);
    expect($ep->invitation_status)->toBe(EstadoInvitacionEvaluador::Rechazada);
});
