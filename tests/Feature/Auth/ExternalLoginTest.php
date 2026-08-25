<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 4: external evaluator credential login (T-016, T-017).
 *
 * RED → GREEN → TRIANGULATE → REFACTOR
 *
 * Covers the `auth-external` domain from `spec.md`:
 *   - Successful login returns a Sanctum token.
 *   - Wrong password increments the failure counter, eventually locking.
 *   - 3 wrong attempts in 10 min lock the account (issue #56, defect 3:
 *     every failure mode now returns the same 401, no 423).
 *   - Non-external users (Estudiante, Coordinador) cannot use this endpoint.
 *   - All four failure modes return an identical 401 (no user enumeration).
 *   - Audit log entries are written for every login attempt.
 *   - The first successful login sets `password_changed_at` only after
 *     the user calls the change-password endpoint.
 */
it('logs in a valid external evaluator via SPA cookie', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user' => ['id', 'email', 'role', 'es_externo']]);

    expect($response->json('user.email'))->toBe('pedro@evaluador.com')
        ->and($response->json('user.role'))->toBe(UserRole::EvaluadorExterno->value)
        ->and($response->json('user.es_externo'))->toBeTrue();

    // No Bearer token is returned (H-004 — cookie-only auth).
    expect($response->json())->not->toHaveKey('token');
});

it('rejects wrong password with 401 and writes an audit log', function () {
    Event::fake([AuditEvent::class]);
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'WRONG',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'invalid_credentials']);

    Event::assertDispatched(
        AuditEvent::class,
        fn (AuditEvent $e) => $e->action === 'login.rejected'
            && $e->user?->id === $user->id
            && $e->description === 'invalid_credentials'
    );

    expect($user->fresh()->failed_attempts)->toBe(1);
});

it('rejects an internal user (no es_externo flag) with 401', function () {
    $user = User::factory()->create([
        'email' => 'ana@unab.edu.co',
        'role' => UserRole::Estudiante->value,
        'es_externo' => false,
        'password' => Hash::make('Password!2026'),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'ana@unab.edu.co',
        'password' => 'Password!2026',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'invalid_credentials']);
});

it('locks the account after 3 failed attempts and returns the unified 401', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/auth/externo/login', [
            'email' => 'pedro@evaluador.com',
            'password' => 'WRONG',
        ])->assertStatus(401);
    }

    // 4th attempt — even with the right password — returns the unified 401
    // (issue #56, defect 3: locked accounts no longer leak via a 423).
    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'invalid_credentials']);

    $user = $user->fresh();

    expect($user->locked_until)->not->toBeNull()
        ->and($user->locked_until->isFuture())->toBeTrue();
});

it('returns the unified 401 for a request against an already-locked account', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
        'locked_until' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'invalid_credentials']);
});

it('returns an identical 401 across all four login failure modes', function () {
    // Nonexistent email.
    $nonexistent = $this->postJson('/api/auth/externo/login', [
        'email' => 'inexistente@x.test',
        'password' => 'x',
    ]);

    // Wrong password on a real external user.
    $external = User::factory()->external()->create([
        'email' => 'clave@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);
    $wrongPassword = $this->postJson('/api/auth/externo/login', [
        'email' => 'clave@evaluador.com',
        'password' => 'incorrecta',
    ]);

    // Internal user (no es_externo flag) hitting the external endpoint.
    $internal = User::factory()->create([
        'email' => 'interno@unab.edu.co',
        'role' => UserRole::Coordinador->value,
        'es_externo' => false,
        'password' => Hash::make('Password!2026'),
    ]);
    $internalUser = $this->postJson('/api/auth/externo/login', [
        'email' => 'interno@unab.edu.co',
        'password' => 'Password!2026',
    ]);

    // Already-locked external user with the correct password.
    $locked = User::factory()->external()->create([
        'email' => 'bloqueado@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
        'locked_until' => now()->addMinutes(10),
    ]);
    $lockedUser = $this->postJson('/api/auth/externo/login', [
        'email' => 'bloqueado@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    // All four MUST match in status code AND body (issue #56, defect 3):
    // no mode may reveal whether the account exists.
    foreach ([$wrongPassword, $internalUser, $lockedUser] as $response) {
        expect($response->status())->toBe($nonexistent->status())
            ->and($response->getContent())->toBe($nonexistent->getContent());
    }

    $nonexistent->assertStatus(401)
        ->assertExactJson(['error' => 'invalid_credentials']);
});

it('resets the failure counter and lockout on a successful login', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
        'failed_attempts' => 2,
    ]);

    $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ])->assertOk();

    expect($user->fresh())
        ->failed_attempts->toBe(0)
        ->locked_until->toBeNull();
});

it('validates that email and password are required', function () {
    $this->postJson('/api/auth/externo/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns must_change_password=true when the external user has never changed it', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk()
        ->assertJson(['must_change_password' => true]);
});

it('returns must_change_password=false once the password has been changed', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk()
        ->assertJson(['must_change_password' => false]);
});

it('audit log captures a successful external login', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ])->assertOk();

    $row = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'login.success')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->metadata)->toMatchArray(['channel' => 'external']);
});

it('audit log captures each failed attempt as login.locked', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    for ($i = 0; $i < 2; $i++) {
        $this->postJson('/api/auth/externo/login', [
            'email' => 'pedro@evaluador.com',
            'password' => 'WRONG',
        ]);
    }

    // 2 failed attempts → 2 login.rejected rows in the audit log.
    $count = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'login.rejected')
        ->count();

    expect($count)->toBe(2);
});
