<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue #40: los endpoints de gestión de usuarios tenían test funcional,
 * pero nunca se verificaba que el registro de auditoría se escribiera.
 * Aquí se cubren las tres acciones: user.deleted, role.changed y
 * user.password_reset (reset-password).
 */

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
});

it('destroyUsuario escribe la auditoría user.deleted', function () {
    $externo = User::factory()->external()->create();

    $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/usuarios/{$externo->id}")
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deleted',
        'user_id' => $this->coordinador->id,
    ]);
});

it('updateUsuario escribe la auditoría role.changed', function () {
    $externo = User::factory()->external()->create();

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$externo->id}", ['role' => 'Estudiante'])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'role.changed',
        'user_id' => $this->coordinador->id,
    ]);
});

it('reset-password escribe la auditoría user.password_reset', function () {
    $externo = User::factory()->external()->create();

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$externo->id}/reset-password")
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.password_reset',
        'user_id' => $this->coordinador->id,
    ]);
});