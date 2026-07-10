<?php

declare(strict_types=1);

use App\Auth\LoginAttemptPolicy;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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

/*
|--------------------------------------------------------------------------
| Issue #13 — sliding window for login lockout
|--------------------------------------------------------------------------
|
| Failed attempts older than `windowMinutes` MUST be discarded before
| the threshold is checked. The previous implementation only counted
| attempts cumulatively — a 3-fail streak from yesterday would still
| lock the account today. The new behaviour:
|
|   - failed attempts within the last `windowMinutes` are cumulative
|   - attempts older than the window are dropped (counter reset to
|     the current attempt)
|   - when the threshold is reached, lockout is set and the counter
|     resets to 0
|
| The user model needs a `last_failed_at` timestamp to anchor the
| window. Without that column, the window is undefined and the
| policy cannot decide which attempts to count.
*/

it('registerFailedLogin() sets last_failed_at on every attempt', function () {
    $user = User::factory()->external()->create(['failed_attempts' => 0]);

    $user->registerFailedLogin();
    $user = $user->fresh();

    expect($user->last_failed_at)->not->toBeNull()
        ->and($user->last_failed_at->isCurrentSecond())->toBeTrue();
});

it('registerFailedLogin() resets the counter when the window has expired', function () {
    // Two old failures (15 min ago — outside the 10-minute window).
    Carbon::setTestNow('2026-07-10 12:00:00');
    $user = User::factory()->external()->create([
        'failed_attempts' => 2,
        'last_failed_at' => now()->subMinutes(15),
    ]);

    // A 3rd attempt 15 minutes later. The old 2 attempts are outside
    // the window and MUST be discarded, so the counter becomes 1
    // (the new attempt), and the account is NOT locked.
    Carbon::setTestNow('2026-07-10 12:15:00');
    $user->registerFailedLogin();
    $user = $user->fresh();

    expect($user->failed_attempts)->toBe(1)
        ->and($user->locked_until)->toBeNull();
});

it('isLocked() returns true when 3 failures occur within the window', function () {
    Carbon::setTestNow('2026-07-10 12:00:00');
    $user = User::factory()->external()->create(['failed_attempts' => 0]);

    for ($i = 0; $i < 3; $i++) {
        $user->registerFailedLogin();
    }
    $user = $user->fresh();

    expect($user->isLocked())->toBeTrue()
        ->and($user->locked_until)->not->toBeNull()
        ->and($user->locked_until->isFuture())->toBeTrue();
});

it('isLocked() returns false when all prior failures are outside the window', function () {
    // 2 old failures, then 15-minute gap, then ask: am I locked?
    Carbon::setTestNow('2026-07-10 12:00:00');
    $user = User::factory()->external()->create([
        'failed_attempts' => 2,
        'last_failed_at' => now()->subMinutes(15),
    ]);

    Carbon::setTestNow('2026-07-10 12:15:00');
    expect($user->fresh()->isLocked())->toBeFalse();
});

it('users table has last_failed_at column for the sliding window', function () {
    // Migration smoke test: the column MUST exist for the window
    // to work. Fails if the migration was never run / never added.
    expect(Schema::hasColumn('users', 'last_failed_at'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Issue #13 — audit action on lockout
|--------------------------------------------------------------------------
|
| The lockout audit log must use the canonical action name `login.locked`
| (defined in `App\Events\AuditEvent`). The old controller was emitting
| `account_locked`, which is non-canonical and not greppable across the
| audit dashboard filters.
*/

it('loginExterno emits login.locked (not account_locked) when the account is locked', function () {
    Carbon::setTestNow('2026-07-10 12:00:00');

    $user = User::factory()->external()->create([
        'email' => 'locked@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
        'failed_attempts' => 3,
        'last_failed_at' => now(),
        'locked_until' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'locked@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertStatus(423);

    // The audit log records the canonical action, not the legacy one.
    expect(AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'login.locked')
        ->exists()
    )->toBeTrue();

    expect(AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'account_locked')
        ->exists()
    )->toBeFalse();
});
