<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);
});

// -- Listar notificaciones ------------------------------------------------

it('listar notificaciones del usuario autenticado', function () {
    Notificacion::create([
        'user_id' => $this->estudiante->id,
        'type' => 'test',
        'title' => 'Notif 1',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);
    Notificacion::create([
        'user_id' => $this->estudiante->id,
        'type' => 'test',
        'title' => 'Notif 2',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);
    // Otra notificacion para otro usuario (no debe aparecer)
    Notificacion::create([
        'user_id' => $this->coordinador->id,
        'type' => 'test',
        'title' => 'No visible',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/notificaciones');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'type', 'title', 'content', 'is_read', 'sent_at']]]);
    expect($response->json('data'))->toHaveCount(2);
});

// -- Notificaciones no leídas ---------------------------------------------

it('no-leidas devuelve count correcto', function () {
    Notificacion::create([
        'user_id' => $this->estudiante->id,
        'type' => 'test',
        'title' => 'No leída',
        'content' => 'Contenido',
        'sent_at' => now(),
        'is_read' => false,
    ]);
    Notificacion::create([
        'user_id' => $this->estudiante->id,
        'type' => 'test',
        'title' => 'Leída',
        'content' => 'Contenido',
        'sent_at' => now(),
        'is_read' => true,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/notificaciones/no-leidas');

    $response->assertOk();
    expect($response->json('data.count'))->toBe(1);
});

// -- Marcar notificación como leída ---------------------------------------

it('marcar leida cambia estado', function () {
    $notificacion = Notificacion::create([
        'user_id' => $this->estudiante->id,
        'type' => 'test',
        'title' => 'Notif',
        'content' => 'Contenido',
        'sent_at' => now(),
        'is_read' => false,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/notificaciones/{$notificacion->id}/leer");

    $response->assertOk();
    expect($notificacion->fresh()->is_read)->toBeTrue();
});

it('no puede marcar leida notificacion de otro usuario (403)', function () {
    $notificacion = Notificacion::create([
        'user_id' => $this->coordinador->id,
        'type' => 'test',
        'title' => 'Notif ajena',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/notificaciones/{$notificacion->id}/leer");

    $response->assertStatus(403);
});

// -- Notificaciones automáticas al crear entrega --------------------------

it('al crear entrega NO se genera notificacion automatica (ahora es por grupo)', function () {
    $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega Test',
            'descripcion' => 'Descripción de la entrega',
            'fecha_limite' => '2026-03-01',
        ]);

    $notificaciones = Notificacion::where('user_id', $this->director->id)->get();
    expect($notificaciones)->toHaveCount(0);
});

// -- Notificaciones automáticas al revisar entrega ------------------------

it('al revisar entrega se genera notificacion para el estudiante', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega a revisar',
        'due_date' => '2026-03-01',
        'status' => 'enviada',
    ]);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", [
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_notes' => 'Buen trabajo',
        ]);

    $notificaciones = Notificacion::where('user_id', $this->estudiante->id)->get();
    expect($notificaciones)->toHaveCount(1);
    expect($notificaciones[0]->type)->toBe('entrega.revisada');
});

// -- Notificaciones automáticas al firmar bitácora ------------------------

it('al firmar bitacora se genera notificacion', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Reunión test',
        'meeting_date' => '2026-04-01',
        'signature_status' => 'Pendiente',
    ]);

    // Estudiante firma primero
    $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");

    $notifEstudiante = Notificacion::where('user_id', $this->estudiante->id)->get();
    expect($notifEstudiante)->toHaveCount(0); // only the other party gets notified

    // Director firma para completar
    $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");

    $notifDirector = Notificacion::where('user_id', $this->director->id)->get();
    expect($notifDirector)->toHaveCount(1);
    expect($notifDirector[0]->type)->toContain('bitacora');
});
