<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;
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

// -- LISTAR ---------------------------------------------------------------

it('lista bitacoras de un proyecto', function () {
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'Bitácora 1', 'meeting_date' => '2026-04-01']);
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'Bitácora 2', 'meeting_date' => '2026-04-02']);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/bitacoras?proyecto_id={$this->proyecto->id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'topic', 'signature_status', 'meeting_date']]]);
    expect($response->json('data'))->toHaveCount(2);
});

it('lista bitacoras requiere proyecto_id', function () {
    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/bitacoras');

    $response->assertStatus(422);
});

it('estudiante solo ve bitacoras de sus proyectos', function () {
    $otroProyecto = Proyecto::create([
        'title' => 'Otro Proyecto',
        'semester_id' => $this->semestre->id,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/bitacoras?proyecto_id={$otroProyecto->id}");

    $response->assertStatus(403);
});

// -- CREAR ----------------------------------------------------------------

it('estudiante puede crear bitacora en su proyecto', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Revisión semanal',
            'notes' => 'Avanzamos en los módulos',
            'meeting_date' => '2026-04-10',
            'duration_hours' => 1.5,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.topic', 'Revisión semanal');
    expect(Bitacora::count())->toBe(1);
    expect(Bitacora::first()->signature_status->value)->toBe('Pendiente');
});

it('crear bitacora requiere campos obligatorios', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['proyecto_id', 'topic', 'meeting_date']);
});

it('estudiante NO puede crear bitacora en proyecto ajeno', function () {
    $otroProyecto = Proyecto::create([
        'title' => 'Ajeno',
        'semester_id' => $this->semestre->id,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $otroProyecto->id,
            'topic' => 'Intrusión',
            'meeting_date' => '2026-04-10',
        ]);

    $response->assertStatus(403);
});

it('director puede crear bitacora en su proyecto', function () {
    $response = $this->actingAs($this->director)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Sprint Planning',
            'meeting_date' => '2026-04-11',
        ]);

    $response->assertCreated();
    expect(Bitacora::count())->toBe(1);
});

// -- VER ---------------------------------------------------------------

it('puede ver bitacora específica', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Detalle',
        'meeting_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/bitacoras/{$bitacora->id}");

    $response->assertOk()
        ->assertJsonPath('data.topic', 'Detalle');
});

it('ver bitacora ajena da 403', function () {
    $otroProyecto = Proyecto::create([
        'title' => 'Ajeno',
        'semester_id' => $this->semestre->id,
        'director_id' => User::factory()->director()->create()->id,
    ]);
    $bitacora = Bitacora::create([
        'proyecto_id' => $otroProyecto->id,
        'topic' => 'Secreta',
        'meeting_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/bitacoras/{$bitacora->id}");

    $response->assertStatus(403);
});

// -- UPDATE ---------------------------------------------------------------

it('puede actualizar su bitácora si está pendiente', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Original',
        'notes' => 'Nota original',
        'meeting_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/bitacoras/{$bitacora->id}", [
            'topic' => 'Actualizado',
            'notes' => 'Nota actualizada',
        ]);

    $response->assertOk();
    expect($bitacora->fresh()->topic)->toBe('Actualizado');
    expect($bitacora->fresh()->notes)->toBe('Nota actualizada');
});

it('NO puede actualizar bitácora ya firmada', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Firmada',
        'meeting_date' => '2026-04-01',
        'signature_status' => 'FirmadaEstudiante',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->putJson("/api/bitacoras/{$bitacora->id}", [
            'topic' => 'Intento',
        ]);

    $response->assertStatus(422);
});

// -- FIRMAR ---------------------------------------------------------------

it('estudiante firma bitácora pendiente -> FirmadaEstudiante', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Para firmar',
        'meeting_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");

    $response->assertOk();
    $b = $bitacora->fresh();
    expect($b->signature_status->value)->toBe('FirmadaEstudiante');
    expect($b->student_signed_at)->not->toBeNull();
});

it('director firma bitácora FirmadaEstudiante -> Completada', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Para completar',
        'meeting_date' => '2026-04-01',
        'signature_status' => 'FirmadaEstudiante',
        'student_signed_at' => now(),
    ]);

    $response = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");

    $response->assertOk();
    $b = $bitacora->fresh();
    expect($b->signature_status->value)->toBe('Completada');
    expect($b->director_signed_at)->not->toBeNull();
});

it('flujo completo: pendiente -> estudiante -> director -> completada', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Flujo completo',
        'meeting_date' => '2026-04-01',
    ]);

    // Estudiante firma
    $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");
    expect($bitacora->fresh()->signature_status->value)->toBe('FirmadaEstudiante');

    // Director firma
    $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");
    expect($bitacora->fresh()->signature_status->value)->toBe('Completada');
});

it('firmar bitácora ya completada da 422', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Completada',
        'meeting_date' => '2026-04-01',
        'signature_status' => 'Completada',
        'student_signed_at' => now(),
        'director_signed_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");

    $response->assertStatus(422);
});

it('persona sin relación al proyecto no puede firmar', function () {
    $intruso = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Para intruso',
        'meeting_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($intruso)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar");

    $response->assertStatus(403);
});
