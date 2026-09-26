<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Anuncio;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * RF-ANU-01 — Group-scoped anuncios.
 *
 * Nullable semestre_id FK on anuncios (null = all groups).
 * Store/update accept semestre_id; index filters by ?semestre_id=.
 */

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestreA = Semestre::factory()->create(['is_active' => true]);
    $this->semestreB = Semestre::factory()->create(['is_active' => true]);
});

it('persists semestre_id when coordinator creates an anuncio with a group', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/anuncios', [
            'title' => 'Aviso grupo A',
            'content' => 'Contenido dirigido',
            'semestre_id' => $this->semestreA->id,
        ]);

    $response->assertCreated();
    expect(Anuncio::firstOrFail()->semestre_id)->toBe($this->semestreA->id);
});

it('stores null semestre_id when no group is given (visible to all)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/anuncios', [
            'title' => 'Aviso global',
            'content' => 'Para todos los grupos',
        ]);

    $response->assertCreated();
    expect(Anuncio::firstOrFail()->semestre_id)->toBeNull();
});

it('filters index by semestre_id showing targeted plus global anuncios only', function () {
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Grupo A',
        'content' => 'Solo A',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => $this->semestreA->id,
    ]);
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Grupo B',
        'content' => 'Solo B',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => $this->semestreB->id,
    ]);
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Global',
        'content' => 'Para todos',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => null,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/anuncios?semestre_id={$this->semestreA->id}");

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title')->sort()->values()->all();
    expect($titles)->toBe(['Global', 'Grupo A']);
});

it('returns all active anuncios when no semestre_id filter is given', function () {
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Grupo A',
        'content' => 'Solo A',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => $this->semestreA->id,
    ]);
    Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Global',
        'content' => 'Para todos',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => null,
    ]);

    $response = $this->actingAs($this->estudiante)->getJson('/api/anuncios');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('persists semestre_id change on update and rejects unknown semestre', function () {
    $anuncio = Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Original',
        'content' => 'Contenido',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => $this->semestreA->id,
    ]);

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/anuncios/{$anuncio->id}", ['semestre_id' => $this->semestreB->id])
        ->assertOk();
    expect($anuncio->fresh()->semestre_id)->toBe($this->semestreB->id);

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/anuncios/{$anuncio->id}", ['semestre_id' => 999999])
        ->assertStatus(422);
});

it('exposes semestre relation on the anuncio model', function () {
    $anuncio = Anuncio::create([
        'author_id' => $this->coordinador->id,
        'title' => 'Con grupo',
        'content' => 'Contenido',
        'published_at' => now(),
        'is_active' => true,
        'semestre_id' => $this->semestreA->id,
    ]);

    expect($anuncio->semestre)->toBeInstanceOf(Semestre::class);
    expect($anuncio->semestre->id)->toBe($this->semestreA->id);
});
