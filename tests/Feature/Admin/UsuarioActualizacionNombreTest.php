<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * El nombre del usuario no se guardaba al editarlo como coordinador.
 *
 * `UpdateUserRequest` solo declaraba `role` y `codigo_estudiante`, así que
 * `$request->validated()` descartaba `name` en silencio (enviado por el
 * frontend) y `updateUsuario` nunca asignaba `$user->name`: la UI confirmaba
 * el guardado y el nombre quedaba con el valor viejo.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->objetivo = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'name' => 'Nombre Anterior',
    ]);
});

it('persiste el nombre al editar un usuario como coordinador', function () {
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$this->objetivo->id}", [
            'name' => 'Nuevo',
            'role' => UserRole::Coordinador->value,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $this->objetivo->id,
        'name' => 'Nuevo',
    ]);

    // El nombre vuelve en el JSON de respuesta.
    expect($response->json('name'))->toBe('Nuevo')
        ->and($this->objetivo->fresh()->name)->toBe('Nuevo');
});

it('rechaza con 422 un nombre vacío en vez de descartarlo en silencio', function () {
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$this->objetivo->id}", [
            'name' => '',
            'role' => UserRole::Coordinador->value,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('name');

    // El nombre viejo sobrevive: un payload inválido no deja datos a medias.
    expect($this->objetivo->fresh()->name)->toBe('Nombre Anterior');
});

it('sigue aceptando la actualización solo de rol sin tocar el nombre', function () {
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$this->objetivo->id}", [
            'role' => UserRole::Director->value,
        ]);

    $response->assertOk();

    expect($this->objetivo->fresh()->role)->toBe(UserRole::Director)
        ->and($this->objetivo->fresh()->name)->toBe('Nombre Anterior');
});

it('actualiza el email y sincroniza la whitelist cuando viene', function () {
    AuthorizedEmail::create([
        'email' => $this->objetivo->email,
        'name' => 'Entrada de whitelist',
        'role' => UserRole::Estudiante->value,
        'created_by' => $this->coordinador->id,
    ]);

    $nuevo = 'renombrado@unab.edu.co';

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$this->objetivo->id}", [
            'name' => 'Nombre Nuevo',
            'email' => $nuevo,
            'role' => UserRole::Director->value,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $this->objetivo->id,
        'email' => $nuevo,
    ]);

    // La whitelist se re-ubica al nuevo email y hereda el rol nuevo.
    $this->assertDatabaseHas('authorized_emails', [
        'email' => $nuevo,
        'role' => UserRole::Director->value,
    ]);
    $this->assertDatabaseMissing('authorized_emails', ['email' => $this->objetivo->email]);
});

it('rechaza con 422 un email duplicado', function () {
    $otro = User::factory()->create();

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$this->objetivo->id}", [
            'name' => 'Nombre Nuevo',
            'email' => $otro->email,
            'role' => UserRole::Director->value,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect($this->objetivo->fresh()->email)->toBe($this->objetivo->email);
});
