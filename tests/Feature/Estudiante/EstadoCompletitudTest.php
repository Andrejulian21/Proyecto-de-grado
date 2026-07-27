<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega Test',
        'description' => 'Desc',
        'due_date' => '2026-09-15',
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento', 'nombre' => 'Documento Principal', 'versionamiento' => true],
            ['slug' => 'anexos', 'nombre' => 'Anexos', 'versionamiento' => false],
            ['slug' => 'cronograma', 'nombre' => 'Cronograma', 'versionamiento' => false],
        ],
    ]);

    $this->entrega->proyectos()->attach($this->proyecto->id);
});

it('reports partial status when some archivos are pending', function () {
    // Upload only 'documento' → 1/3 complete, 2 pending
    $file = UploadedFile::fake()->create('doc.pdf', 100);
    $this->actingAs($this->estudiante)
        ->postJson("/api/entregas/{$this->entrega->id}/archivos/documento", ['file' => $file])
        ->assertStatus(201);

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/entregas/{$this->entrega->id}/estado");

    $response->assertOk();
    $data = $response->json('data');

    expect($data['total_archivos'])->toBe(3);
    expect($data['completos'])->toBe(1);
    expect($data['pendientes'])->toBe(2);

    // 'documento' should be marked complete
    $docDetalle = collect($data['detalle'])->firstWhere('slug', 'documento');
    expect($docDetalle['completado'])->toBeTrue();

    // 'anexos' and 'cronograma' should be pending
    $anexosDetalle = collect($data['detalle'])->firstWhere('slug', 'anexos');
    expect($anexosDetalle['completado'])->toBeFalse();

    $cronoDetalle = collect($data['detalle'])->firstWhere('slug', 'cronograma');
    expect($cronoDetalle['completado'])->toBeFalse();
});

it('reports complete status when all archivos are uploaded', function () {
    $pivot = EntregaProyecto::firstOrCreate(
        ['entrega_id' => $this->entrega->id, 'proyecto_id' => $this->proyecto->id],
        ['estado' => 'pendiente']
    );

    // Manually create versions for all 3 archivos.
    // Use unique version_numbers to avoid UNIQUE(entrega_id, version_number) constraint.
    foreach (['documento', 'anexos', 'cronograma'] as $i => $slug) {
        VersionDocumento::create([
            'entrega_id' => $this->entrega->id,
            'entrega_proyecto_id' => $pivot->id,
            'archivo_requerido_id' => $slug,
            'version_number' => $i + 1,
            'file_path' => "entregas/{$this->entrega->id}/{$slug}/v{$i}_test.pdf",
            'file_size' => 1024,
            'original_name' => "{$slug}.pdf",
            'uploaded_at' => now(),
            'descontinuado' => false,
        ]);
    }

    $response = $this->actingAs($this->estudiante)
        ->getJson("/api/entregas/{$this->entrega->id}/estado");

    $response->assertOk();
    $data = $response->json('data');

    expect($data['total_archivos'])->toBe(3);
    expect($data['completos'])->toBe(3);
    expect($data['pendientes'])->toBe(0);

    foreach ($data['detalle'] as $detalle) {
        expect($detalle['completado'])->toBeTrue();
    }
});
