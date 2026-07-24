<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Estudiante Dashboard',
        'semester_id' => $this->semestre->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);
});

it('entregas incluye titulo real y array de versiones con observacion', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Documento técnico de anteproyecto',
        'due_date' => '2026-03-15',
        'status' => 'enviada',
    ]);

    VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/v1.pdf',
        'original_name' => 'anteproyecto-v1.pdf',
        'director_notes' => 'Corregir introducción y ampliar metodología del estudio.',
        'uploaded_at' => now()->subDay(),
    ]);

    VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 2,
        'file_path' => 'entregas/v2.pdf',
        'original_name' => 'anteproyecto-v2.pdf',
        'director_notes' => null,
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $entrega->id);

    expect($item)->not->toBeNull();
    expect($item['titulo'])->toBe('Documento técnico de anteproyecto');
    expect($item['versiones'])->toHaveCount(2);
    expect($item['versiones'][0]['numero_version'])->toBe(1);
    expect($item['versiones'][0]['nombre_archivo'])->toBe('anteproyecto-v1.pdf');
    expect($item['versiones'][0]['observacion'])->toContain('Corregir introducción');
    expect($item['versiones'][0]['subido_en'])->not->toBeNull();
    expect($item['versiones'][1]['numero_version'])->toBe(2);
    expect($item['versiones'][1]['observacion'])->toBeNull();
});

it('entregas sin versiones devuelve array vacio', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'desarrollo',
        'title' => 'Informe de avance',
        'due_date' => '2026-04-01',
        'status' => 'pendiente',
    ]);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $entrega->id);

    expect($item['titulo'])->toBe('Informe de avance');
    expect($item['versiones'])->toBe([]);
    expect($item['total_versiones'])->toBe(0);
});
