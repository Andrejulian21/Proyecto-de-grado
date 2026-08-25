<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Policies\EntregaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * EntregaPolicy (issue #38): default-deny authorization.
 *
 * Central rule: a user may access an entrega when they are a Coordinador,
 * the director of a linked project, a student of a linked project, or an
 * evaluator assigned to a linked project. Everything else is denied.
 *
 * Coordination-only abilities (create/update/delete/manage) and the
 * director/student abilities (review/habilitar/solicitar/deleteVersion)
 * keep the behaviour that the controllers already enforced.
 */
function crearEntregaConContexto(): array
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

    EvaluadorProyecto::create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'invitation_status' => 'Aceptada',
        'assigned_at' => now(),
        'evaluado' => false,
    ]);

    // Production shape (StoreEntregaAction): semester_id set, proyecto_id
    // never assigned, project linked through the entrega_proyecto pivot.
    $entrega = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega',
        'due_date' => now()->addMonths(1)->toDateString(),
        'status' => 'pendiente',
    ]);
    $entrega->proyectos()->attach($proyecto->id);

    return compact('semestre', 'director', 'estudiante', 'evaluador', 'coordinador', 'ajeno', 'proyecto', 'entrega');
}

it('EntregaPolicy::view permite a coordinador, director, estudiante y evaluador asignado', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    expect($policy->view($ctx['coordinador'], $ctx['entrega']))->toBeTrue()
        ->and($policy->view($ctx['director'], $ctx['entrega']))->toBeTrue()
        ->and($policy->view($ctx['estudiante'], $ctx['entrega']))->toBeTrue()
        ->and($policy->view($ctx['evaluador'], $ctx['entrega']))->toBeTrue()
        ->and($policy->view($ctx['ajeno'], $ctx['entrega']))->toBeFalse();
});

it('EntregaPolicy::view niega a evaluador no asignado y a director ajeno', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    $evaluadorSinAsignar = User::factory()->external()->create(['password_changed_at' => now()]);
    $directorAjeno = User::factory()->director()->create();

    expect($policy->view($evaluadorSinAsignar, $ctx['entrega']))->toBeFalse()
        ->and($policy->view($directorAjeno, $ctx['entrega']))->toBeFalse();
});

it('EntregaPolicy::view respeta el vínculo por pivote entrega_proyecto', function () {
    $semestre = Semestre::factory()->create(['is_active' => true]);
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $director = User::factory()->director()->create();

    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    $proyecto->estudiantes()->attach($estudiante->id);

    $entrega = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega por pivote',
        'due_date' => now()->addMonths(1)->toDateString(),
        'status' => 'pendiente',
    ]);
    $entrega->proyectos()->attach($proyecto->id);

    $policy = new EntregaPolicy;

    expect($policy->view($estudiante, $entrega))->toBeTrue()
        ->and($policy->view($director, $entrega))->toBeTrue();
});

it('EntregaPolicy::review permite solo al director del proyecto', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    expect($policy->review($ctx['director'], $ctx['entrega']))->toBeTrue()
        ->and($policy->review($ctx['coordinador'], $ctx['entrega']))->toBeFalse()
        ->and($policy->review($ctx['estudiante'], $ctx['entrega']))->toBeFalse()
        ->and($policy->review($ctx['evaluador'], $ctx['entrega']))->toBeFalse();
});

it('EntregaPolicy::habilitar permite solo al director del proyecto', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    expect($policy->habilitar($ctx['director'], $ctx['entrega']))->toBeTrue()
        ->and($policy->habilitar($ctx['coordinador'], $ctx['entrega']))->toBeFalse()
        ->and($policy->habilitar($ctx['estudiante'], $ctx['entrega']))->toBeFalse()
        ->and($policy->habilitar($ctx['evaluador'], $ctx['entrega']))->toBeFalse();
});

it('EntregaPolicy::solicitar permite solo al estudiante del proyecto', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    expect($policy->solicitar($ctx['estudiante'], $ctx['entrega']))->toBeTrue()
        ->and($policy->solicitar($ctx['director'], $ctx['entrega']))->toBeFalse()
        ->and($policy->solicitar($ctx['coordinador'], $ctx['entrega']))->toBeFalse()
        ->and($policy->solicitar($ctx['evaluador'], $ctx['entrega']))->toBeFalse();
});

it('EntregaPolicy::deleteVersion permite solo al estudiante del proyecto', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    expect($policy->deleteVersion($ctx['estudiante'], $ctx['entrega']))->toBeTrue()
        ->and($policy->deleteVersion($ctx['director'], $ctx['entrega']))->toBeFalse()
        ->and($policy->deleteVersion($ctx['coordinador'], $ctx['entrega']))->toBeFalse()
        ->and($policy->deleteVersion($ctx['evaluador'], $ctx['entrega']))->toBeFalse();
});

it('EntregaPolicy::create, update, delete y manage permiten solo al coordinador', function () {
    $ctx = crearEntregaConContexto();
    $policy = new EntregaPolicy;

    expect($policy->create($ctx['coordinador']))->toBeTrue()
        ->and($policy->create($ctx['estudiante']))->toBeFalse()
        ->and($policy->create($ctx['director']))->toBeFalse()
        ->and($policy->update($ctx['coordinador']))->toBeTrue()
        ->and($policy->update($ctx['director']))->toBeFalse()
        ->and($policy->update($ctx['estudiante']))->toBeFalse()
        ->and($policy->delete($ctx['coordinador']))->toBeTrue()
        ->and($policy->delete($ctx['estudiante']))->toBeFalse()
        ->and($policy->delete($ctx['director']))->toBeFalse()
        ->and($policy->manage($ctx['coordinador']))->toBeTrue()
        ->and($policy->manage($ctx['director']))->toBeFalse()
        ->and($policy->manage($ctx['estudiante']))->toBeFalse();
});
