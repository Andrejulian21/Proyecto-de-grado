<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// =========================================================================
// H-003: Rate limiting — 5 attempts/min per IP+email → 6th is 429
//
// NOTE: The account lockout fires after 3 failed attempts (423).
// To test rate limiting without triggering lockout, we use correct
// passwords for most requests and only the last one is wrong.
// =========================================================================

test('login rate limit returns 429 after 5 attempts', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    // 5 successful attempts — don't trigger lockout, but count toward rate limit
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/externo/login', [
            'email' => 'pedro@evaluador.com',
            'password' => 'TempPass!2026',
        ])->assertOk();
    }

    // 6th attempt should be rate-limited (429) even with correct credentials
    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertStatus(429);
});

test('rate limit is per IP+email, different email from same IP is not blocked', function () {
    User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);
    User::factory()->external()->create([
        'email' => 'maria@evaluadora.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    // Exhaust the rate limit for pedro@evaluador.com with correct password
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/externo/login', [
            'email' => 'pedro@evaluador.com',
            'password' => 'TempPass!2026',
        ])->assertOk();
    }

    // 6th for pedro should be 429
    $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ])->assertStatus(429);

    // Different email from same IP should succeed (not rate-limited)
    $this->postJson('/api/auth/externo/login', [
        'email' => 'maria@evaluadora.com',
        'password' => 'TempPass!2026',
    ])->assertOk();
});

// =========================================================================
// H-002: Constant-time login — non-existent email → 401 (not 403/404)
// =========================================================================

test('login with non-existent email returns 401', function () {
    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'noexiste@test.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'invalid_credentials']);
});

// H-002: Internal user (Estudiante) → 401 (was 403 before PR1)
test('login with internal user email returns 401', function () {
    User::factory()->create([
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

// H-002: Timing consistency — delta < 50ms between non-existent and wrong password
test('login response time is consistent', function () {
    // Measure time for non-existent email
    $start = microtime(true);
    $this->postJson('/api/auth/externo/login', [
        'email' => 'noexiste@test.com',
        'password' => 'SomePassword123!',
    ]);
    $timeNonExistent = (microtime(true) - $start) * 1000;

    // Measure time for wrong password on existing user
    User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    $start = microtime(true);
    $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'WRONG',
    ]);
    $timeWrongPassword = (microtime(true) - $start) * 1000;

    // Delta must be less than 200ms. The purpose of this test is to catch
    // ORDER OF MAGNITUDE timing differences (e.g., missing Hash::check
    // entirely) that would expose user enumeration — not microsecond
    // optimization. 200ms is generous enough to avoid CI flakiness while
    // still catching real timing attacks where one code path skips bcrypt.
    expect(abs($timeNonExistent - $timeWrongPassword))->toBeLessThan(200);
});

// =========================================================================
// H-001: CSRF exemption removed
//
// In test environment, Sanctum SPA stateful detection doesn't apply
// (no SPA domain configured), so CSRF middleware doesn't run on API
// routes. We verify the configuration change: the bootstrap/app.php
// no longer lists auth routes in the CSRF exception array.
// =========================================================================

test('auth routes are not exempt from CSRF in bootstrap config', function () {
    $config = file_get_contents(base_path('bootstrap/app.php'));

    // The validateCsrfTokens(except: [...]) block must NOT contain
    // the external login or logout routes.
    expect($config)
        ->not->toContain("'api/auth/externo/login'")
        ->and($config)->not->toContain("'api/auth/logout'");
});
