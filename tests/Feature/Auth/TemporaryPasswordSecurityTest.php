<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Issue #42 — external evaluator temporary passwords.
 *
 * Product decision: the temporary password must be stored HASHED in
 * `users.last_temp_password` (never plaintext), must not be exposed by
 * the public user listing, and the coordinator recovers access to it by
 * REGENERATING it through the coordinator-only reset-password endpoint
 * (the original is not recoverable from a bcrypt hash).
 */
it('stores a bcrypt hash instead of plaintext on evaluator creation', function () {
    $coord = User::factory()->coordinador()->create();
    $pass = 'MiPassword123!';

    $response = $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
            'password' => $pass,
            'password_confirmation' => $pass,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('temporary_password', $pass);

    $user = User::query()->where('email', 'pedro@evaluador.com')->firstOrFail();

    expect($user->last_temp_password)
        ->not->toBe($pass)
        ->and(Hash::check($pass, $user->last_temp_password))->toBeTrue();
});

it('stores a bcrypt hash instead of plaintext on password reset', function () {
    $coord = User::factory()->coordinador()->create();
    $user = User::factory()->external()->create([
        'name' => 'Evaluador Reset',
        'email' => 'reset@evaluador.com',
    ]);

    $response = $this->actingAs($coord)
        ->putJson("/api/admin/usuarios/{$user->id}/reset-password");

    $response->assertStatus(200);
    $newPassword = $response->json('new_password');
    expect($newPassword)->toBeString()->not->toBeEmpty();

    $user->refresh();

    expect($user->last_temp_password)
        ->not->toBe($newPassword)
        ->and(Hash::check($newPassword, $user->last_temp_password))->toBeTrue();
});

it('does not expose last_temp_password in the public user listing', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
            'password' => 'MiPassword123!',
            'password_confirmation' => 'MiPassword123!',
        ])
        ->assertStatus(201);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/usuarios?per_page=50');

    $response->assertOk()
        ->assertJsonMissingPath('data.0.last_temp_password');

    // Belt-and-suspenders: no element in the payload carries the field.
    foreach ($response->json('data') as $item) {
        expect($item)->not->toHaveKey('last_temp_password');
    }
});

it('hides last_temp_password from model serialization', function () {
    $user = User::factory()->external()->create([
        'last_temp_password' => Hash::make('ClaveTemporal123!'),
    ]);

    $serialized = $user->toArray();

    expect($serialized)->not->toHaveKey('last_temp_password');
});

it('ignores a mass-assigned last_temp_password on evaluator creation', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
            'password' => 'MiPassword123!',
            'password_confirmation' => 'MiPassword123!',
            'last_temp_password' => 'InyectadoPorCliente',
        ])
        ->assertStatus(201);

    $user = User::query()->where('email', 'pedro@evaluador.com')->firstOrFail();

    expect($user->last_temp_password)
        ->not->toBe('InyectadoPorCliente')
        ->and(Hash::check('MiPassword123!', $user->last_temp_password))->toBeTrue();
});

it('non-coordinators cannot reset an external evaluator password (403)', function () {
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $user = User::factory()->external()->create();

    $this->actingAs($student)
        ->putJson("/api/admin/usuarios/{$user->id}/reset-password")
        ->assertStatus(403);

    $this->actingAs($user)
        ->putJson("/api/admin/usuarios/{$user->id}/reset-password")
        ->assertStatus(403);
});

it('reset-password is rejected for non-external users', function () {
    $coord = User::factory()->coordinador()->create();
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($coord)
        ->putJson("/api/admin/usuarios/{$student->id}/reset-password")
        ->assertStatus(422);
});
