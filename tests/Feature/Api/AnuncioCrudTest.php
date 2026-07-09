<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Anuncio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
});

// -- Listar anuncios (público) -------------------------------------------

it('coordinador puede listar anuncios', function () {
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Aviso 1',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/anuncios');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title', 'content', 'published_at']]]);
    expect($response->json('data'))->toHaveCount(1);
});

it('estudiante puede listar anuncios', function () {
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Aviso 1',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/anuncios');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('listar anuncios solo muestra activos', function () {
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Activo',
        'content' => 'Contenido',
        'published_at' => now(),
        'is_active' => true,
    ]);
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Inactivo',
        'content' => 'Contenido',
        'published_at' => now(),
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/anuncios');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data')[0]['title'])->toBe('Activo');
});

// -- Crear anuncio (solo coordinador) ------------------------------------

it('coordinador puede crear anuncio', function () {
    $payload = [
        'title' => 'Nuevo aviso',
        'content' => 'Contenido del aviso',
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/anuncios', $payload);

    $response->assertCreated()
        ->assertJson(['data' => ['title' => 'Nuevo aviso']]);
    expect(Anuncio::count())->toBe(1);
});

it('crear anuncio valida campos requeridos', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/anuncios', []);

    $response->assertStatus(422);
});

it('estudiante NO puede crear anuncio (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/admin/anuncios', [
            'title' => 'Hack',
            'content' => 'Intento',
        ]);

    $response->assertStatus(403);
});

// -- Actualizar anuncio (solo coordinador) --------------------------------

it('coordinador puede actualizar anuncio', function () {
    $anuncio = Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Original',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/anuncios/{$anuncio->id}", [
            'title' => 'Actualizado',
        ]);

    $response->assertOk();
    expect($anuncio->fresh()->title)->toBe('Actualizado');
});

it('estudiante NO puede actualizar anuncio (403)', function () {
    $anuncio = Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Original',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/admin/anuncios/{$anuncio->id}", [
            'title' => 'Hack',
        ]);

    $response->assertStatus(403);
});

// -- Eliminar anuncio (solo coordinador) ----------------------------------

it('coordinador puede eliminar anuncio', function () {
    $anuncio = Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'A eliminar',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/anuncios/{$anuncio->id}");

    $response->assertOk();
    expect(Anuncio::count())->toBe(0);
});

it('estudiante NO puede eliminar anuncio (403)', function () {
    $anuncio = Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'A eliminar',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->deleteJson("/api/admin/anuncios/{$anuncio->id}");

    $response->assertStatus(403);
});
