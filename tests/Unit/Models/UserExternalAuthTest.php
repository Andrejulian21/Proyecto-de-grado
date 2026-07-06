<?php

declare(strict_types=1);

use App\Auth\LoginAttemptPolicy;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 3: external-evaluator lockout + forced password
 * change helpers on the User model (T-016, T-017).
 */
it('isLocked() returns false when locked_until is null', function () {
    $user = User::factory()->external()->create();

    expect($user->isLocked())->toBeFalse();
});

it('isLocked() returns true when locked_until is in the future', function () {
    $user = User::factory()->external()->create([
        'locked_until' => now()->addMinutes(10),
    ]);

    expect($user->isLocked())->toBeTrue();
});

it('isLocked() returns false when locked_until is in the past', function () {
    $user = User::factory()->external()->create([
        'locked_until' => now()->subMinute(),
    ]);

    expect($user->isLocked())->toBeFalse();
});

it('mustChangePassword() returns true for external users who never changed it', function () {
    $user = User::factory()->external()->create([
        'password_changed_at' => null,
    ]);

    expect($user->mustChangePassword())->toBeTrue();
});

it('mustChangePassword() returns false once the external user has changed their password', function () {
    $user = User::factory()->external()->create([
        'password_changed_at' => now(),
    ]);

    expect($user->mustChangePassword())->toBeFalse();
});

it('mustChangePassword() returns false for internal users (Google OAuth)', function () {
    $user = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'es_externo' => false,
        'password_changed_at' => null,
    ]);

    expect($user->mustChangePassword())->toBeFalse();
});

it('registerFailedLogin() increments failed_attempts', function () {
    $user = User::factory()->external()->create(['failed_attempts' => 0]);

    $user->registerFailedLogin();

    expect($user->fresh()->failed_attempts)->toBe(1);
});

it('registerFailedLogin() locks the account after max attempts', function () {
    $user = User::factory()->external()->create(['failed_attempts' => 0]);

    // 3 failed attempts → lock
    for ($i = 0; $i < 3; $i++) {
        $user->registerFailedLogin();
    }

    $user = $user->fresh();

    expect($user->locked_until)->not->toBeNull()
        ->and($user->locked_until->isFuture())->toBeTrue()
        ->and($user->failed_attempts)->toBe(0); // counter resets on lock
});

it('registerFailedLogin() respects a custom policy', function () {
    $this->app->instance(LoginAttemptPolicy::class, new LoginAttemptPolicy(
        maxAttempts: 2,
        windowMinutes: 10,
        lockMinutes: 30,
    ));

    $user = User::factory()->external()->create(['failed_attempts' => 0]);

    $user->registerFailedLogin();
    $user->registerFailedLogin();

    $user = $user->fresh();

    expect($user->locked_until)->not->toBeNull()
        ->and(now()->diffInMinutes($user->locked_until))->toBeGreaterThanOrEqual(29);
});

it('clearFailedLogin() resets the counter and lockout window', function () {
    $user = User::factory()->external()->create([
        'failed_attempts' => 2,
        'locked_until' => now()->addMinutes(10),
    ]);

    $user->clearFailedLogin();
    $user = $user->fresh();

    expect($user->failed_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull();
});

it('LoginAttemptPolicy exposes the spec values by default', function () {
    $policy = new LoginAttemptPolicy;

    expect($policy->maxAttempts())->toBe(3)
        ->and($policy->windowMinutes())->toBe(10)
        ->and($policy->lockMinutes())->toBe(15);
});
