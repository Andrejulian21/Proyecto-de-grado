<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue #58 — UserPolicy (T-019) must be invoked through the real
 * request path, not just exercised in isolation.
 *
 * These tests verify that only a Coordinador can view/update/delete
 * users via the UserController, using actingAs to properly set the
 * authenticated user context.
 */
it('UserPolicy::viewAny allows coordinator and denies other roles', function () {
    $coordinator = User::factory()->create([
        'role' => UserRole::Coordinador,
        'es_externo' => false,
    ]);
    $student = User::factory()->create([
        'role' => UserRole::Estudiante,
        'es_externo' => false,
    ]);

    $this->actingAs($coordinator)
        ->getJson('/api/admin/usuarios')
        ->assertOk();

    $this->actingAs($student)
        ->getJson('/api/admin/usuarios')
        ->assertForbidden();
});

it('UserPolicy::delete allows coordinator and denies other roles', function () {
    $coordinator = User::factory()->create([
        'role' => UserRole::Coordinador,
        'es_externo' => false,
    ]);
    $student = User::factory()->create([
        'role' => UserRole::Estudiante,
        'es_externo' => false,
    ]);

    // Coordinator → policy passes → user is deleted.
    $target = User::factory()->external()->create();
    $this->actingAs($coordinator)
        ->deleteJson("/api/admin/usuarios/{$target->id}")
        ->assertOk();

    expect(User::find($target->id))->toBeNull();

    // Non-coordinator → policy denies → 403, user is untouched.
    $target2 = User::factory()->external()->create();
    $this->actingAs($student)
        ->deleteJson("/api/admin/usuarios/{$target2->id}")
        ->assertForbidden();

    expect(User::find($target2->id))->not->toBeNull();
});
