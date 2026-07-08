<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 12: Gates + UserPolicy (T-019).
 *
 * Gates:
 *   - manage-users  : Coordinador only
 *   - view-admin    : Coordinador + Director
 *
 * UserPolicy methods:
 *   - viewAny, create, update, delete  : Coordinador only
 */
it('manage-users gate allows Coordinador', function () {
    $coord = User::factory()->coordinador()->create();

    expect(Gate::forUser($coord)->allows('manage-users'))->toBeTrue();
});

it('manage-users gate denies Estudiante, Director, EvaluadorExterno', function () {
    foreach ([UserRole::Estudiante, UserRole::Director, UserRole::EvaluadorExterno] as $role) {
        $user = User::factory()->create(['role' => $role->value]);
        expect(Gate::forUser($user)->allows('manage-users'))
            ->toBeFalse("manage-users should deny {$role->value}");
    }
});

it('view-admin gate allows Coordinador and Director', function () {
    foreach ([UserRole::Coordinador, UserRole::Director] as $role) {
        $user = User::factory()->create(['role' => $role->value]);
        expect(Gate::forUser($user)->allows('view-admin'))
            ->toBeTrue("view-admin should allow {$role->value}");
    }
});

it('view-admin gate denies Estudiante and EvaluadorExterno', function () {
    foreach ([UserRole::Estudiante, UserRole::EvaluadorExterno] as $role) {
        $user = User::factory()->create(['role' => $role->value]);
        expect(Gate::forUser($user)->allows('view-admin'))
            ->toBeFalse("view-admin should deny {$role->value}");
    }
});

it('UserPolicy::viewAny allows Coordinador and denies everyone else', function () {
    $policy = new UserPolicy;

    $coord = User::factory()->coordinador()->create();
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    expect($policy->viewAny($coord))->toBeTrue()
        ->and($policy->viewAny($student))->toBeFalse();
});

it('UserPolicy::create allows Coordinador and denies everyone else', function () {
    $policy = new UserPolicy;

    $coord = User::factory()->coordinador()->create();
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    expect($policy->create($coord))->toBeTrue()
        ->and($policy->create($student))->toBeFalse();
});

it('UserPolicy::update allows Coordinador and denies everyone else', function () {
    $policy = new UserPolicy;

    $coord = User::factory()->coordinador()->create();
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $target = User::factory()->create();

    expect($policy->update($coord, $target))->toBeTrue()
        ->and($policy->update($student, $target))->toBeFalse();
});

it('UserPolicy::delete allows Coordinador and denies everyone else', function () {
    $policy = new UserPolicy;

    $coord = User::factory()->coordinador()->create();
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $target = User::factory()->create();

    expect($policy->delete($coord, $target))->toBeTrue()
        ->and($policy->delete($student, $target))->toBeFalse();
});
