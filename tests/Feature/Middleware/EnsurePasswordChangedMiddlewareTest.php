<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 8: EnsurePasswordChanged middleware (T-017).
 *
 * External evaluators that have not yet changed their temporary
 * password are blocked from every authenticated endpoint except
 * `POST /api/auth/change-password`. The middleware runs AFTER
 * `auth:sanctum` so the user is resolved before the check.
 */

beforeEach(function () {
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsurePasswordChanged::class])
        ->get('/api/_protected', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsurePasswordChanged::class])
        ->post('/api/auth/change-password', fn () => response()->json(['ok' => true]))
        ->name('auth.change_password');
});

it('allows an internal user (Google OAuth) without restriction', function () {
    $internal = User::factory()->create();

    $this->actingAs($internal)
        ->getJson('/api/_protected')
        ->assertOk();
});

it('allows an external user who has already changed their password', function () {
    $external = User::factory()->external()->create([
        'password' => Hash::make('NewPass!2099'),
        'password_changed_at' => now()->subDay(),
    ]);

    $this->actingAs($external)
        ->getJson('/api/_protected')
        ->assertOk();
});

it('blocks an external user who still has the temporary password', function () {
    $external = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($external)
        ->getJson('/api/_protected')
        ->assertStatus(403)
        ->assertJson(['error' => 'password_change_required']);
});

it('allows the blocked user to call /api/auth/change-password', function () {
    $external = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($external)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'TempPass!2026',
            'new_password' => 'NewPass!2099',
            'new_password_confirmation' => 'NewPass!2099',
        ])
        ->assertOk();
});
