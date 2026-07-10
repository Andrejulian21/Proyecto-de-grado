<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// =========================================================================
// H-007: Server-side temp_password is 16 chars
// =========================================================================

test('server-side temp_password is at least 16 characters', function () {
    $coord = User::factory()->coordinador()->create();

    $response = $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
        ]);

    $response->assertStatus(201);

    $password = $response->json('temporary_password');
    expect($password)->toBeString()
        ->and(strlen($password))->toBeGreaterThanOrEqual(16);
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
