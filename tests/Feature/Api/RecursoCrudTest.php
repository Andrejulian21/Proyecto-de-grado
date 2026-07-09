<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\RecursoInformativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
});

// -- Listar recursos (todos los roles) -----------------------------------

it('coordinador puede listar recursos', function () {
    RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Guía 1',
        'category' => 'manual',
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/recursos');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title', 'category']]]);
    expect($response->json('data'))->toHaveCount(1);
});

it('estudiante puede listar recursos', function () {
    RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Guía 1',
        'category' => 'manual',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/recursos');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

// -- Crear recurso (solo coordinador) ------------------------------------

it('coordinador puede crear recurso', function () {
    $payload = [
        'title' => 'Nueva guía',
        'category' => 'documentacion',
        'description' => 'Descripción de la guía',
        'link' => 'https://ejemplo.com/guia',
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/recursos', $payload);

    $response->assertCreated()
        ->assertJson(['data' => ['title' => 'Nueva guía']]);
    expect(RecursoInformativo::count())->toBe(1);
});

it('crear recurso valida campos requeridos', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/recursos', []);

    $response->assertStatus(422);
});

it('estudiante NO puede crear recurso (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/admin/recursos', [
            'title' => 'Hack',
            'category' => 'test',
        ]);

    $response->assertStatus(403);
});

// -- Actualizar recurso (solo coordinador) --------------------------------

it('coordinador puede actualizar recurso', function () {
    $recurso = RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Original',
        'category' => 'manual',
    ]);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/recursos/{$recurso->id}", [
            'title' => 'Actualizado',
        ]);

    $response->assertOk();
    expect($recurso->fresh()->title)->toBe('Actualizado');
});

it('estudiante NO puede actualizar recurso (403)', function () {
    $recurso = RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Original',
        'category' => 'manual',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/admin/recursos/{$recurso->id}", [
            'title' => 'Hack',
        ]);

    $response->assertStatus(403);
});

// -- Eliminar recurso (solo coordinador) ----------------------------------

it('coordinador puede eliminar recurso', function () {
    $recurso = RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'A eliminar',
        'category' => 'manual',
    ]);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/recursos/{$recurso->id}");

    $response->assertOk();
    expect(RecursoInformativo::count())->toBe(0);
});

it('estudiante NO puede eliminar recurso (403)', function () {
    $recurso = RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'A eliminar',
        'category' => 'manual',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->deleteJson("/api/admin/recursos/{$recurso->id}");

    $response->assertStatus(403);
});

// -- Acceder a recurso incrementa access_count ---------------------------

it('acceder a recurso incrementa access_count', function () {
    $recurso = RecursoInformativo::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Guía popular',
        'category' => 'manual',
    ]);

    expect($recurso->access_count)->toBe(0);

    $this->actingAs($this->estudiante)
        ->getJson("/api/recursos/{$recurso->id}")
        ->assertOk();

    expect($recurso->fresh()->access_count)->toBe(1);

    $this->actingAs($this->coordinador)
        ->getJson("/api/recursos/{$recurso->id}")
        ->assertOk();

    expect($recurso->fresh()->access_count)->toBe(2);
});
