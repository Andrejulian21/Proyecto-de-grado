<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 6: RoleMiddleware (T-018).
 *
 * The middleware accepts a comma-separated list of allowed roles
 * (e.g. `role:Coordinador,Director`). It blocks users with a
 * different role, returns 403, and writes an audit log.
 */

beforeEach(function () {
    // Test-only ping endpoints used to exercise the middleware
    // without coupling to the real admin route bodies.
    Route::middleware(['auth:sanctum', 'role:Coordinador'])
        ->get('/api/admin/_ping', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'role:Coordinador,Director'])
        ->get('/api/admin-or-director/_ping', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'role:EvaluadorExterno'])
        ->get('/api/evaluador/_ping', fn () => response()->json(['ok' => true]));
});

it('allows a request from a user with the required role', function () {
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($coord)
        ->getJson('/api/admin/_ping')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('rejects a request from a user with a different role (403)', function () {
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($student)
        ->getJson('/api/admin/_ping')
        ->assertStatus(403)
        ->assertJson(['error' => 'unauthorized']);
});

it('accepts any of multiple allowed roles', function () {
    $director = User::factory()->director()->create();
    $coord = User::factory()->coordinador()->create();

    $this->actingAs($director)
        ->getJson('/api/admin-or-director/_ping')
        ->assertOk();

    $this->actingAs($coord)
        ->getJson('/api/admin-or-director/_ping')
        ->assertOk();
});

it('writes an audit log when access is denied', function () {
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($student)
        ->getJson('/api/admin/_ping')
        ->assertStatus(403);

    $row = AuditLog::query()
        ->where('user_id', $student->id)
        ->where('action', 'access.denied')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->description)->toBe('role_mismatch')
        ->and($row->metadata)->toMatchArray([
            'required_roles' => ['Coordinador'],
            'actual_role' => 'Estudiante',
        ]);
});

it('blocks unauthenticated requests with 401 (auth:sanctum runs first)', function () {
    $this->getJson('/api/admin/_ping')
        ->assertStatus(401);
});

it('allows EvaluadorExterno on a role:EvaluadorExterno route', function () {
    $evaluador = User::factory()->external()->create();

    $this->actingAs($evaluador)
        ->getJson('/api/evaluador/_ping')
        ->assertOk();
});
