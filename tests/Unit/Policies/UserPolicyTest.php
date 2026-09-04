<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 12: UserPolicy (T-019, H-009).
 *
 * Gates (manage-users, view-admin) removed in H-009 — they had no
 * call-sites in production code. Role enforcement is handled by the
 * `role:Coordinador` route middleware.
 *
 * UserPolicy methods retained:
 *   - viewAny, create, update, delete  : Coordinador only
 */
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
