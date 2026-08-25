<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Policies\BitacoraPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * BitacoraPolicy (issue #38): default-deny authorization adapted to
 * bitácoras — Coordinador, director of the project, or student of the
 * project. External evaluators never get bitácora access.
 */
function crearBitacoraConContexto(): array
{
    $semestre = Semestre::factory()->create(['is_active' => true]);
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
    $coordinador = User::factory()->coordinador()->create();
    $ajeno = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    $proyecto->estudiantes()->attach($estudiante->id);

    $bitacora = Bitacora::create([
        'proyecto_id' => $proyecto->id,
        'topic' => 'Bitácora',
        'meeting_date' => now()->toDateString(),
        'signature_status' => 'Pendiente',
    ]);

    return compact('semestre', 'director', 'estudiante', 'evaluador', 'coordinador', 'ajeno', 'proyecto', 'bitacora');
}

it('BitacoraPolicy::view permite a coordinador, director y estudiante; niega a evaluador y ajeno', function () {
    $ctx = crearBitacoraConContexto();
    $policy = new BitacoraPolicy;

    expect($policy->view($ctx['coordinador'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->view($ctx['director'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->view($ctx['estudiante'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->view($ctx['evaluador'], $ctx['bitacora']))->toBeFalse()
        ->and($policy->view($ctx['ajeno'], $ctx['bitacora']))->toBeFalse();
});

it('BitacoraPolicy::view acepta un Proyecto como recurso (listado por proyecto)', function () {
    $ctx = crearBitacoraConContexto();
    $policy = new BitacoraPolicy;

    expect($policy->view($ctx['estudiante'], $ctx['proyecto']))->toBeTrue()
        ->and($policy->view($ctx['evaluador'], $ctx['proyecto']))->toBeFalse()
        ->and($policy->view($ctx['ajeno'], $ctx['proyecto']))->toBeFalse();
});

it('BitacoraPolicy::create permite a coordinador, director y estudiante del proyecto', function () {
    $ctx = crearBitacoraConContexto();
    $policy = new BitacoraPolicy;

    expect($policy->create($ctx['coordinador'], $ctx['proyecto']))->toBeTrue()
        ->and($policy->create($ctx['director'], $ctx['proyecto']))->toBeTrue()
        ->and($policy->create($ctx['estudiante'], $ctx['proyecto']))->toBeTrue()
        ->and($policy->create($ctx['evaluador'], $ctx['proyecto']))->toBeFalse()
        ->and($policy->create($ctx['ajeno'], $ctx['proyecto']))->toBeFalse();
});

it('BitacoraPolicy::update permite a coordinador, director y estudiante del proyecto', function () {
    $ctx = crearBitacoraConContexto();
    $policy = new BitacoraPolicy;

    expect($policy->update($ctx['coordinador'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->update($ctx['director'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->update($ctx['estudiante'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->update($ctx['evaluador'], $ctx['bitacora']))->toBeFalse()
        ->and($policy->update($ctx['ajeno'], $ctx['bitacora']))->toBeFalse();
});

it('BitacoraPolicy::delete permite solo a coordinador o director del proyecto', function () {
    $ctx = crearBitacoraConContexto();
    $policy = new BitacoraPolicy;

    expect($policy->delete($ctx['coordinador'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->delete($ctx['director'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->delete($ctx['estudiante'], $ctx['bitacora']))->toBeFalse()
        ->and($policy->delete($ctx['evaluador'], $ctx['bitacora']))->toBeFalse();
});

it('BitacoraPolicy::sign permite solo al director del proyecto (issue derivada #45)', function () {
    $ctx = crearBitacoraConContexto();
    $policy = new BitacoraPolicy;

    expect($policy->sign($ctx['director'], $ctx['bitacora']))->toBeTrue()
        ->and($policy->sign($ctx['coordinador'], $ctx['bitacora']))->toBeFalse()
        ->and($policy->sign($ctx['estudiante'], $ctx['bitacora']))->toBeFalse()
        ->and($policy->sign($ctx['evaluador'], $ctx['bitacora']))->toBeFalse()
        ->and($policy->sign($ctx['ajeno'], $ctx['bitacora']))->toBeFalse();
});
