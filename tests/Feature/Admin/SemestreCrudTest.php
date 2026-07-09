<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
});

it('coordinador puede listar semestres', function () {
    Semestre::create(['name' => '2025-1', 'start_date' => '2025-02-01', 'end_date' => '2025-06-30']);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/semestres');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'start_date', 'end_date', 'is_active']],
        ]);
    expect($response->json('data'))->toHaveCount(1);
});

it('coordinador puede crear semestre', function () {
    $payload = [
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/semestres', $payload);

    $response->assertCreated()
        ->assertJson(['data' => ['name' => '2026-1']]);
    expect(Semestre::count())->toBe(1);
});

it('coordinador puede actualizar semestre', function () {
    $semestre = Semestre::create(['name' => '2025-1', 'start_date' => '2025-02-01', 'end_date' => '2025-06-30']);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/semestres/{$semestre->id}", ['name' => '2025-A']);

    $response->assertOk();
    expect($semestre->fresh()->name)->toBe('2025-A');
});

it('coordinador puede eliminar semestre', function () {
    $semestre = Semestre::create(['name' => '2025-1', 'start_date' => '2025-02-01', 'end_date' => '2025-06-30']);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/semestres/{$semestre->id}");

    $response->assertOk();
    expect(Semestre::count())->toBe(0);
});

it('estudiante NO puede crear semestre (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/admin/semestres', [
            'name' => '2026-1',
            'start_date' => '2026-02-01',
            'end_date' => '2026-06-30',
        ]);

    $response->assertStatus(403);
});

it('máximo 2 semestres activos — al activar un tercero da error', function () {
    Semestre::create(['name' => '2025-1', 'start_date' => '2025-02-01', 'end_date' => '2025-06-30', 'is_active' => true]);
    Semestre::create(['name' => '2025-2', 'start_date' => '2025-08-01', 'end_date' => '2025-11-30', 'is_active' => true]);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/semestres', [
            'name' => '2026-1',
            'start_date' => '2026-02-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
});

it('al desactivar semestre no afecta proyectos existentes', function () {
    $semestre = Semestre::create(['name' => '2025-1', 'start_date' => '2025-02-01', 'end_date' => '2025-06-30', 'is_active' => true]);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/semestres/{$semestre->id}", ['is_active' => false]);

    $response->assertOk();
    expect($semestre->fresh()->is_active)->toBeFalse();
});
