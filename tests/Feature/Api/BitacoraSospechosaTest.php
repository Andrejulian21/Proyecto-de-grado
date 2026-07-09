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

test('marca como sospechosa cuando director firma mas de 3 en 5 minutos', function () {
    // Create 4 bitacoras, all pre-signed by the student
    $bitacoras = [];
    for ($i = 1; $i <= 4; $i++) {
        $b = Bitacora::create([
            'proyecto_id' => $this->proyecto->id,
            'topic' => "Bitácora {$i}",
            'meeting_date' => '2026-04-01',
            'signature_status' => 'FirmadaEstudiante',
            'student_signed_at' => now(),
        ]);
        $bitacoras[] = $b;
    }

    // Director signs first 3 — no problem yet
    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacoras[$i]->id}/firmar");
    }

    expect($bitacoras[0]->fresh()->signature_status->value)->toBe('Completada');
    expect($bitacoras[1]->fresh()->signature_status->value)->toBe('Completada');
    expect($bitacoras[2]->fresh()->signature_status->value)->toBe('Completada');

    // Director signs the 4th — all 4 should become Sospechosa
    $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacoras[3]->id}/firmar");

    foreach ($bitacoras as $b) {
        expect($b->fresh()->signature_status->value)->toBe('Sospechosa');
    }
});

test('no marca sospechosa si firma dentro de 3 en 5 minutos', function () {
    $bitacoras = [];
    for ($i = 1; $i <= 3; $i++) {
        $b = Bitacora::create([
            'proyecto_id' => $this->proyecto->id,
            'topic' => "Bitácora {$i}",
            'meeting_date' => '2026-04-01',
            'signature_status' => 'FirmadaEstudiante',
            'student_signed_at' => now(),
        ]);
        $bitacoras[] = $b;
    }

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacoras[$i]->id}/firmar");
    }

    expect($bitacoras[0]->fresh()->signature_status->value)->toBe('Completada');
    expect($bitacoras[1]->fresh()->signature_status->value)->toBe('Completada');
    expect($bitacoras[2]->fresh()->signature_status->value)->toBe('Completada');
});
