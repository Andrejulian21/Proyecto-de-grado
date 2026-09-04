<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue #53: la gestión de usuarios truncaba a 200 porque el frontend
 * pedía per_page=200 y paginaba en el cliente. El backend ya paginaba;
 * este test documenta que el servidor soporta más de 200 usuarios
 * navegando páginas (el frontend ahora acumula páginas).
 */
it('soporta más de 200 usuarios vía paginación del servidor', function () {
    $coordinador = User::factory()->coordinador()->create();
    User::factory()->count(205)->create(['role' => UserRole::Estudiante->value]);

    $page1 = $this->actingAs($coordinador)
        ->getJson('/api/admin/usuarios?per_page=200');

    $page1->assertOk();
    expect($page1->json('data'))->toHaveCount(200);
    expect($page1->json('total'))->toBe(206); // 205 estudiantes + coordinador
    expect($page1->json('last_page'))->toBe(2);

    $page2 = $this->actingAs($coordinador)
        ->getJson('/api/admin/usuarios?per_page=200&page=2');

    $page2->assertOk();
    expect($page2->json('data'))->toHaveCount(6);
});
