<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $this->proyecto->estudiantes()->attach($this->estudiante->id);
});

// ----------------------------------------------------------------------
// PR 4 — RF-WK-03: semana validation on creation
// ----------------------------------------------------------------------

it('estudiante puede crear bitacora con semana valida en rango 1..32', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Semana 5',
            'meeting_date' => '2026-04-10',
            'semana' => 5,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.semana', 5);
    expect(Bitacora::first()->semana)->toBe(5);
});

it('acepta el limite inferior del rango (semana = 1)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Inicio',
            'meeting_date' => '2026-04-10',
            'semana' => 1,
        ]);

    $response->assertCreated();
});

it('acepta el limite superior del rango (semana = 32)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Final',
            'meeting_date' => '2026-04-10',
            'semana' => 32,
        ]);

    $response->assertCreated();
});

it('crear bitacora con semana duplicada en el mismo proyecto da 422', function () {
    Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Original',
        'meeting_date' => '2026-04-01',
        'semana' => 10,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Duplicada',
            'meeting_date' => '2026-04-02',
            'semana' => 10,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['semana']);
});

it('misma semana en proyectos diferentes es permitida', function () {
    $otroProyecto = Proyecto::create([
        'title' => 'Otro Proyecto',
        'semester_id' => $this->semestre->id,
    ]);
    $otroProyecto->estudiantes()->attach($this->estudiante->id);

    Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'A',
        'meeting_date' => '2026-04-01',
        'semana' => 7,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $otroProyecto->id,
            'topic' => 'B',
            'meeting_date' => '2026-04-02',
            'semana' => 7,
        ]);

    $response->assertCreated();
    expect(Bitacora::where('proyecto_id', $otroProyecto->id)->value('semana'))->toBe(7);
});

it('rechaza semana = 0 (fuera de rango inferior)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Semana 0',
            'meeting_date' => '2026-04-10',
            'semana' => 0,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['semana']);
});

it('rechaza semana = 33 (fuera de rango superior)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Semana 33',
            'meeting_date' => '2026-04-10',
            'semana' => 33,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['semana']);
});

it('rechaza semana no entera', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Semana fraccionaria',
            'meeting_date' => '2026-04-10',
            'semana' => 5.5,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['semana']);
});

it('rechaza semana faltante', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Sin semana',
            'meeting_date' => '2026-04-10',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['semana']);
});

// ----------------------------------------------------------------------
// PR 4 — RF-WK-04: 15-minute edit window
// ----------------------------------------------------------------------

it('permite actualizar una bitacora creada hace menos de 15 minutos', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Original',
        'meeting_date' => '2026-04-10',
        'semana' => 1,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/bitacoras/{$bitacora->id}", [
            'topic' => 'Actualizado dentro de ventana',
        ]);

    $response->assertOk();
    expect($bitacora->fresh()->topic)->toBe('Actualizado dentro de ventana');
});

it('rechaza actualizar una bitacora creada hace mas de 15 minutos', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Vieja',
        'meeting_date' => '2026-04-01',
        'semana' => 1,
    ]);

    // Backdate created_at 20 minutes into the past so the 15-min window
    // has clearly expired. We use forceFill so the timestamp writes
    // through Eloquent's timestamp guard.
    $bitacora->forceFill(['created_at' => now()->subMinutes(20)])->save();

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/bitacoras/{$bitacora->id}", [
            'topic' => 'Intento fuera de ventana',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath(
            'error',
            'El tiempo de edición ha expirado (15 minutos desde la creación).',
        );
    expect($bitacora->fresh()->topic)->toBe('Vieja');
});

it('la ventana de 15 min se evalua contra created_at, no contra updated_at', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Topic',
        'meeting_date' => '2026-04-01',
        'semana' => 2,
    ]);

    // Backdate ONLY created_at, leave updated_at as 'now' so we can
    // prove the controller uses created_at for the check.
    $bitacora->forceFill(['created_at' => now()->subMinutes(20)])->save();

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/bitacoras/{$bitacora->id}", [
            'topic' => 'No debe aplicarse',
        ]);

    $response->assertStatus(422);
});
