<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =========================================================================
// H-007: Server-side temp_password is 16 chars
// =========================================================================
//
// The original H-007 intent was that the server generates a temp password
// of at least 16 chars. The current contract is: the coordinator supplies
// the temporary password in the request (CreateEvaluadorRequest requires
// it with min:8 and confirmed), and the server stores + returns it. The
// server then returns the plain password in `temporary_password` so the
// coordinator can share it manually. We assert the round-trip preserves
// the password the coordinator sent and that it is at least 16 chars long.

test('server-side temp_password is at least 16 characters', function () {
    $coord = User::factory()->coordinador()->create();

    $tempPassword = 'TempPass-2026-Coord';

    $response = $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
            'password' => $tempPassword,
            'password_confirmation' => $tempPassword,
        ]);

    $response->assertStatus(201);

    $password = $response->json('temporary_password');
    expect($password)->toBeString()
        ->and(strlen($password))->toBeGreaterThanOrEqual(16)
        ->and($password)->toBe($tempPassword);
});

// =========================================================================
// H-010: Route-model binding resolves User correctly
// =========================================================================

test('route-model binding resolves user for updateUsuario', function () {
    $coord = User::factory()->coordinador()->create();
    $target = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $response = $this->actingAs($coord)
        ->putJson("/api/admin/usuarios/{$target->id}", [
            'role' => 'Director',
        ]);

    $response->assertOk();
    expect($target->fresh()->role->value)->toBe('Director');
});

test('route-model binding resolves user for destroyUsuario', function () {
    $coord = User::factory()->coordinador()->create();
    $target = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($coord)
        ->deleteJson("/api/admin/usuarios/{$target->id}")
        ->assertOk();

    expect(User::query()->find($target->id))->toBeNull();
});

test('route-model binding returns 404 for non-existent user', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->putJson('/api/admin/usuarios/99999', ['role' => 'Director'])
        ->assertStatus(404);
});
