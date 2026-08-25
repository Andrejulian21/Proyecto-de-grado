<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('la descarga de un archivo sin autenticación devuelve 401', function () {
    Storage::fake('public');
    Storage::disk('public')->put('entregas/1/anteproyecto/v1_documento.pdf', 'contenido del pdf');

    $this->getJson('/storage/entregas/1/anteproyecto/v1_documento.pdf')
        ->assertUnauthorized();
});

test('una descarga de navegador sin autenticación redirige a login', function () {
    Storage::fake('public');
    Storage::disk('public')->put('entregas/1/anteproyecto/v1_documento.pdf', 'contenido del pdf');

    $this->get('/storage/entregas/1/anteproyecto/v1_documento.pdf')
        ->assertRedirect(route('login'));
});

test('un usuario autenticado puede descargar un archivo existente', function () {
    Storage::fake('public');
    Storage::disk('public')->put('entregas/1/anteproyecto/v1_documento.pdf', 'contenido del pdf');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/storage/entregas/1/anteproyecto/v1_documento.pdf');

    $response->assertOk();

    ob_start();
    $response->baseResponse->sendContent();
    $servedContent = (string) ob_get_clean();

    expect($servedContent)->toBe('contenido del pdf');
});

test('un intento de path traversal con segmentos .. devuelve 403', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/storage/%2e%2e%2f%2e%2e%2f%2e%2e%2f.env')
        ->assertForbidden();
});

test('una ruta de archivo inexistente devuelve 404', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/storage/entregas/999/anteproyecto/no-existe.pdf')
        ->assertNotFound();
});
