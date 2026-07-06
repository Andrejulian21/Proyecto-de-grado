<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 5: coordinator creates external evaluator (T-017).
 *
 * The admin endpoint creates a user with `es_externo = true`,
 * generates a random temporary password, and returns both the user
 * payload and the plain password so the coordinator can share it
 * manually. Every action writes an audit log.
 */
it('coordinator can create an external evaluator with a generated password', function () {
    $coord = User::factory()->coordinador()->create();

    $response = $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'role', 'es_externo'],
            'temporary_password',
        ]);

    expect($response->json('user.role'))->toBe(UserRole::EvaluadorExterno->value)
        ->and($response->json('user.es_externo'))->toBeTrue()
        ->and($response->json('user.email'))->toBe('pedro@evaluador.com');

    // The temporary password is a non-empty string (we don't enforce
    // a specific length, but it should look like a generated token).
    $password = $response->json('temporary_password');
    expect($password)->toBeString()
        ->and(strlen($password))->toBeGreaterThanOrEqual(8);

    // The user exists in the DB and the password hashes correctly.
    $user = User::query()->where('email', 'pedro@evaluador.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->mustChangePassword())->toBeTrue();
});

it('validates that name and email are required', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);
});

it('rejects a duplicate email with 422', function () {
    $coord = User::factory()->coordinador()->create();
    User::factory()->external()->create(['email' => 'pedro@evaluador.com']);

    $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Dup',
            'email' => 'pedro@evaluador.com',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('non-coordinador cannot create external evaluators (403)', function () {
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($student)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Forbidden',
            'email' => 'pedro@evaluador.com',
        ])
        ->assertStatus(403);
});

it('an unauthenticated client cannot create evaluators (401)', function () {
    $this->postJson('/api/admin/evaluadores', [
        'name' => 'Pedro Anonymous',
        'email' => 'pedro@evaluador.com',
    ])->assertStatus(401);
});

it('audit log captures user.created_external on creation', function () {
    $coord = User::factory()->coordinador()->create();

    $response = $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Evaluador',
            'email' => 'pedro@evaluador.com',
        ]);

    $createdUser = User::query()->where('email', 'pedro@evaluador.com')->first();

    $row = AuditLog::query()
        ->where('user_id', $coord->id)
        ->where('action', 'user.created_external')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->metadata)->toMatchArray([
            'created_user_id' => $createdUser->id,
            'created_email' => 'pedro@evaluador.com',
        ]);
});

it('validates that the email is a valid format', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Bad Email',
            'email' => 'not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
