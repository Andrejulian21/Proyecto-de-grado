<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 7: changePassword endpoint + EnsurePasswordChanged
 * middleware (T-017, also referenced by T-014).
 */
it('authenticated external user can change their own password', function () {
    $user = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'TempPass!2026',
            'new_password' => 'NewPass!2099',
            'new_password_confirmation' => 'NewPass!2099',
        ]);

    $response->assertOk()->assertJson(['ok' => true]);

    $user = $user->fresh();

    expect(Hash::check('NewPass!2099', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and($user->mustChangePassword())->toBeFalse();
});

it('rejects an incorrect current password (422)', function () {
    $user = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'WRONG',
            'new_password' => 'NewPass!2099',
            'new_password_confirmation' => 'NewPass!2099',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

it('requires the new password confirmation (422)', function () {
    $user = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'TempPass!2026',
            'new_password' => 'NewPass!2099',
            'new_password_confirmation' => 'Mismatch',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['new_password']);
});

it('enforces a minimum length on the new password (422)', function () {
    $user = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'TempPass!2026',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['new_password']);
});

it('audits the password change', function () {
    $user = User::factory()->external()->create([
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'TempPass!2026',
            'new_password' => 'NewPass!2099',
            'new_password_confirmation' => 'NewPass!2099',
        ])->assertOk();

    $row = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'password.changed')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->metadata)->toMatchArray(['channel' => 'external']);
});

it('rejects unauthenticated change-password requests (401)', function () {
    $this->postJson('/api/auth/change-password', [
        'current_password' => 'x',
        'new_password' => 'NewPass!2099',
        'new_password_confirmation' => 'NewPass!2099',
    ])->assertStatus(401);
});
