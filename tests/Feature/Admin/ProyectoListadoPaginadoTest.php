<?php

declare(strict_types=1);

use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue #53: el listado de proyectos no estaba paginado en el servidor.
 * Este test fija el nuevo contrato: el endpoint responde un paginador
 * (data + total + per_page + current_page + last_page) y respeta
 * per_page/page.
 */
beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::factory()->create();
});

it('lista proyectos paginados en el servidor', function () {
    Proyecto::factory()->count(25)->create(['semester_id' => $this->semestre->id]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos?per_page=10');

    $response->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page', 'last_page']);

    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('total'))->toBe(25);
    expect($response->json('per_page'))->toBe(10);
    expect($response->json('last_page'))->toBe(3);
    expect($response->json('current_page'))->toBe(1);
});

it('navega a la página 2 de proyectos', function () {
    Proyecto::factory()->count(25)->create(['semester_id' => $this->semestre->id]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos?per_page=10&page=2');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('current_page'))->toBe(2);
});

it('respeta el límite máximo de per_page (200)', function () {
    Proyecto::factory()->count(5)->create(['semester_id' => $this->semestre->id]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos?per_page=9999');

    $response->assertOk();
    expect($response->json('per_page'))->toBe(200);
});

it('sigue filtrando por grupo con la paginación', function () {
    $otroSemestre = Semestre::factory()->create();
    Proyecto::factory()->count(3)->create(['semester_id' => $this->semestre->id]);
    Proyecto::factory()->count(2)->create(['semester_id' => $otroSemestre->id]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos?grupo_id='.$this->semestre->id);

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(3);
    expect(Proyecto::where('semester_id', $this->semestre->id)->pluck('id'))
        ->every(fn ($id) => $ids->contains($id));
});
