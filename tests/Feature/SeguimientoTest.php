<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->create(['role' => UserRole::Coordinador->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);
});

it('rejects seguimiento for unauthenticated users', function () {
    $this->getJson("/api/admin/seguimiento/semestre/{$this->semestre->id}")
        ->assertStatus(401);
});

it('returns seguimiento data for coordinator', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson("/api/admin/seguimiento/semestre/{$this->semestre->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'semestre' => ['id', 'nombre'],
                'proyectos',
            ],
        ]);
});

it('rejects seguimiento for non-coordinator', function () {
    $user = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($user)
        ->getJson("/api/admin/seguimiento/semestre/{$this->semestre->id}")
        ->assertStatus(403);
});
