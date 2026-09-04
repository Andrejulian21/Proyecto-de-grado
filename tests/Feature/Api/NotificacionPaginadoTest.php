<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue #53: /api/notificaciones no estaba paginado y la tabla crece una
 * fila por evento y por usuario. Este test fija el nuevo contrato del
 * paginador (data + total + per_page + last_page).
 */
beforeEach(function () {
    $this->usuario = User::factory()->create(['role' => UserRole::Estudiante->value]);
});

it('lista notificaciones paginadas en el servidor', function () {
    foreach (range(1, 25) as $i) {
        Notificacion::create([
            'user_id' => $this->usuario->id,
            'type' => 'test',
            'title' => "Notif {$i}",
            'content' => 'Contenido',
            'sent_at' => now()->subMinutes(25 - $i),
        ]);
    }

    $response = $this->actingAs($this->usuario)
        ->getJson('/api/notificaciones?per_page=10');

    $response->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page', 'last_page']);

    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('total'))->toBe(25);
    expect($response->json('per_page'))->toBe(10);
    expect($response->json('last_page'))->toBe(3);

    // Más recientes primero.
    $sentAt = collect($response->json('data'))->pluck('sent_at')->all();
    expect($sentAt)->toBe(collect($sentAt)->sortDesc()->values()->all());
});

it('solo devuelve notificaciones del usuario autenticado', function () {
    Notificacion::create([
        'user_id' => $this->usuario->id,
        'type' => 'test',
        'title' => 'Mía',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);
    $otro = User::factory()->create(['role' => UserRole::Coordinador->value]);
    Notificacion::create([
        'user_id' => $otro->id,
        'type' => 'test',
        'title' => 'Ajena',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($this->usuario)
        ->getJson('/api/notificaciones');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Mía');
});
