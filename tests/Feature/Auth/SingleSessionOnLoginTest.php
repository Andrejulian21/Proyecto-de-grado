<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 10: single-session enforcement on login (T-021).
 *
 * When a user logs in, all prior Sanctum tokens for that user MUST
 * be deleted before the new token is issued. This is enforced in
 * the AuthController::loginExterno method.
 */
it('loginExterno deletes prior tokens for the same user', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    // Device A: existing token.
    $user->createToken('device-a');
    expect($user->fresh()->tokens)->toHaveCount(1);

    // Device B: new login.
    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk();

    // The old token is gone; only the new one remains.
    expect($user->fresh()->tokens)->toHaveCount(1)
        ->and($user->fresh()->tokens->first()->name)->toBe('external-evaluator');
});
