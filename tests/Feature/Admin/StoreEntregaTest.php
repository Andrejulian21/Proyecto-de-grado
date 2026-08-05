<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
});

it('creates one entrega linked to all projects in the semester', function () {
    // Create 3 projects in the semester
    $proyectos = Proyecto::factory(3)->create([
        'semester_id' => $this->semestre->id,
    ]);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega Test',
            'descripcion' => 'Descripción',
            'fecha_limite' => '2026-08-15',
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
            ],
        ]);

    $response->assertStatus(201);

    // Single entrega created
    expect(Entrega::count())->toBe(1);

    $entrega = Entrega::first();
    expect($entrega->proyectos->count())->toBe(3);
    expect($entrega->archivos_requeridos)->toBeArray();
    expect($entrega->archivos_requeridos[0]['slug'] ?? $entrega->archivos_requeridos[0]['id'])->toBe('documento-proyecto');
});

it('validates at least one archivo requerido', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Test',
            'descripcion' => 'Desc',
            'fecha_limite' => '2026-08-15',
            'archivos_requeridos' => [],
        ]);

    $response->assertStatus(422);
});

it('validates max 6 archivos requeridos', function () {
    $archivos = [];

    for ($i = 0; $i < 7; $i++) {
        $archivos[] = ['id' => "doc_$i", 'nombre' => "Doc $i", 'versionamiento' => true];
    }

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Test',
            'descripcion' => 'Desc',
            'fecha_limite' => '2026-08-15',
            'archivos_requeridos' => $archivos,
        ]);

    $response->assertStatus(422);
});

it('validates unique slugs in archivos_requeridos', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Test',
            'descripcion' => 'Desc',
            'fecha_limite' => '2026-08-15',
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
                ['id' => 'documento-proyecto', 'nombre' => 'Otro', 'versionamiento' => false],
            ],
        ]);

    $response->assertStatus(422);
});

it('persiste fecha de inicio, hora de inicio y hora máxima al crear', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega con ventana',
            'descripcion' => 'Descripción',
            'fecha_limite' => '2026-08-20',
            'fecha_inicio' => '2026-08-05',
            'hora_inicio' => '09:00',
            'hora_maxima' => '18:30',
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
            ],
        ]);

    $response->assertStatus(201);

    $entrega = Entrega::first();
    expect($entrega->start_date->toDateString())->toBe('2026-08-05');
    expect($entrega->start_time)->toBe('09:00');
    expect($entrega->hora_maxima)->toBe('18:30');

    // The response must echo the persisted window so the UI can lock/disable.
    expect($response->json('data.start_date'))->not->toBeNull();
    expect($response->json('data.start_time'))->toBe('09:00');
    expect($response->json('data.hora_maxima'))->toBe('18:30');
});
