<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 13: Whitelist CRUD (T-020).
 *
 *   - GET    /api/admin/whitelist         → paginated list
 *   - POST   /api/admin/whitelist         → add (must be @unab.edu.co)
 *   - PUT    /api/admin/whitelist/{id}    → change role, sync users.role
 *   - DELETE /api/admin/whitelist/{id}    → remove (soft delete via delete)
 *
 * Every action gated by `role:Coordinador` and writes an audit log.
 */
it('GET /api/admin/whitelist returns a paginated list', function () {
    $coord = User::factory()->coordinador()->create();
    AuthorizedEmail::create(['email' => 'maria@unab.edu.co', 'role' => UserRole::Estudiante->value]);
    AuthorizedEmail::create(['email' => 'juan@unab.edu.co', 'role' => UserRole::Director->value]);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/whitelist');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'email', 'role', 'created_by', 'created_at', 'updated_at']],
        ]);

    expect($response->json('data'))->toHaveCount(2);
});

it('POST /api/admin/whitelist creates a new entry and writes audit log', function () {
    $coord = User::factory()->coordinador()->create();

    $response = $this->actingAs($coord)
        ->postJson('/api/admin/whitelist', [
            'email' => 'nuevo@unab.edu.co',
            'role' => UserRole::Estudiante->value,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'email', 'role']);

    $row = AuditLog::query()->where('action', 'whitelist.add')->first();
    expect($row)->not->toBeNull()
        ->and($row->metadata)->toMatchArray([
            'email' => 'nuevo@unab.edu.co',
            'role' => 'Estudiante',
        ]);
});

it('POST rejects a non-UNAB email with 422', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/whitelist', [
            'email' => 'externo@gmail.com',
            'role' => UserRole::Estudiante->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'El correo debe ser institucional (@unab.edu.co).');
});

it('POST accepts a case-insensitive institutional email', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/whitelist', [
            'email' => 'Nuevo.Estudiante@UNAB.EDU.CO',
            'name' => 'Nuevo Estudiante',
            'role' => UserRole::Estudiante->value,
        ])
        ->assertStatus(201)
        ->assertJsonPath('email', 'nuevo.estudiante@unab.edu.co');
});

it('POST rejects a duplicate email with 422', function () {
    $coord = User::factory()->coordinador()->create();
    AuthorizedEmail::create(['email' => 'dup@unab.edu.co', 'role' => UserRole::Estudiante->value]);

    $this->actingAs($coord)
        ->postJson('/api/admin/whitelist', [
            'email' => 'dup@unab.edu.co',
            'role' => UserRole::Director->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('PUT updates the role of an existing entry and syncs the user role', function () {
    $coord = User::factory()->coordinador()->create();
    $entry = AuthorizedEmail::create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);
    $existingUser = User::factory()->create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);

    $response = $this->actingAs($coord)
        ->putJson("/api/admin/whitelist/{$entry->id}", [
            'role' => UserRole::Director->value,
        ]);

    $response->assertOk();

    $entry = $entry->fresh();
    expect($entry->role)->toBe(UserRole::Director)
        ->and($existingUser->fresh()->role)->toBe(UserRole::Director);

    $row = AuditLog::query()->where('action', 'whitelist.role_changed')->first();
    expect($row)->not->toBeNull()
        ->and($row->description)->toContain('Estudiante')
        ->and($row->description)->toContain('Director');
});

it('DELETE removes the entry and writes audit log', function () {
    $coord = User::factory()->coordinador()->create();
    $entry = AuthorizedEmail::create([
        'email' => 'juan@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);

    $response = $this->actingAs($coord)
        ->deleteJson("/api/admin/whitelist/{$entry->id}");

    $response->assertOk();
    expect(AuthorizedEmail::query()->where('id', $entry->id)->exists())->toBeFalse();

    $row = AuditLog::query()->where('action', 'whitelist.removed')->first();
    expect($row)->not->toBeNull()
        ->and($row->metadata)->toMatchArray(['email' => 'juan@unab.edu.co']);
});

it('non-coordinador cannot manage the whitelist (403)', function () {
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($student)
        ->getJson('/api/admin/whitelist')
        ->assertStatus(403);

    $this->actingAs($student)
        ->postJson('/api/admin/whitelist', [
            'email' => 'shouldfail@unab.edu.co',
            'role' => UserRole::Estudiante->value,
        ])
        ->assertStatus(403);
});

it('PUT returns 404 when the entry does not exist', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->putJson('/api/admin/whitelist/9999', ['role' => UserRole::Director->value])
        ->assertStatus(404);
});

it('POST validates that role is one of the allowed values', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/whitelist', [
            'email' => 'test@unab.edu.co',
            'role' => 'Hacker',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});
