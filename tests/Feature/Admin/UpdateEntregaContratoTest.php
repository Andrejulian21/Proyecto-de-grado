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
    $this->proyecto = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
});

/**
 * Seed an entrega in the canonical persisted shape (archivos_requeridos
 * JSON uses `slug`, not `id` — see StoreEntregaAction).
 */
function seedEntregaEditable(int $semestreId, ?int $proyectoId = null): Entrega
{
    return Entrega::create([
        'semester_id' => $semestreId,
        'proyecto_id' => $proyectoId,
        'phase' => 'anteproyecto',
        'title' => 'Título original',
        'description' => 'Descripción original',
        'due_date' => now()->addMonths(1)->toDateString(),
        'start_date' => null,
        'start_time' => null,
        'acceptance_criteria' => null,
        'hora_maxima' => null,
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ]);
}

it('update persiste los campos canónicos en español', function () {
    $entrega = seedEntregaEditable($this->semestre->id, $this->proyecto->id);
    $nuevaFechaLimite = now()->addMonths(2)->toDateString();
    $nuevaFechaInicio = now()->addMonth()->toDateString();

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$entrega->id}", [
            'titulo' => 'Título editado',
            'descripcion' => 'Descripción editada',
            'fecha_limite' => $nuevaFechaLimite,
            'fecha_inicio' => $nuevaFechaInicio,
            'hora_inicio' => '09:30',
            'criterios' => 'Criterios editados',
            'hora_maxima' => '18:00',
            'fase' => 'anteproyecto',
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true, 'analizable_ia' => true],
            ],
        ]);

    $response->assertOk();
    $entrega->refresh();

    expect($entrega->title)->toBe('Título editado');
    expect($entrega->description)->toBe('Descripción editada');
    expect($entrega->due_date->toDateString())->toBe($nuevaFechaLimite);
    expect($entrega->start_date->toDateString())->toBe($nuevaFechaInicio);
    expect($entrega->start_time)->toBe('09:30');
    expect($entrega->acceptance_criteria)->toBe('Criterios editados');
    expect($entrega->hora_maxima)->toBe('18:00');

    $principal = collect($entrega->archivos_requeridos)->firstWhere('slug', 'documento-proyecto');
    expect($principal['analizable_ia'])->toBeTrue();
});

it('update acepta archivos con slug en lugar de id (alias)', function () {
    $entrega = seedEntregaEditable($this->semestre->id, $this->proyecto->id);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$entrega->id}", [
            'archivos_requeridos' => [
                ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
                ['slug' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false],
            ],
        ]);

    $response->assertOk();
    $entrega->refresh();

    $slugs = collect($entrega->archivos_requeridos)->pluck('slug')->all();
    expect($slugs)->toContain('documento-proyecto');
    expect($slugs)->toContain('anexo');
});

it('store acepta archivos con slug en lugar de id (alias)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega',
            'descripcion' => 'Descripción',
            'fecha_limite' => now()->addMonths(1)->toDateString(),
            'archivos_requeridos' => [
                ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
            ],
        ]);

    $response->assertCreated();
    expect(Entrega::first()->archivos_requeridos[0]['slug'])->toBe('documento-proyecto');
});

it('store rechaza analizable_ia en secundario incluso con slug (RF-ENT-02)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', [
            'grupo_id' => $this->semestre->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega',
            'descripcion' => 'Descripción',
            'fecha_limite' => now()->addMonths(1)->toDateString(),
            'archivos_requeridos' => [
                ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
                ['slug' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false, 'analizable_ia' => true],
            ],
        ]);

    $response->assertStatus(422);
    expect($response->json('errors.archivos_requeridos.0'))
        ->toContain("Solo el archivo 'documento del proyecto' puede ser analizable con IA");
});

it('update ignora las claves legacy del contrato anterior', function () {
    $entrega = seedEntregaEditable($this->semestre->id, $this->proyecto->id);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$entrega->id}", [
            'description' => 'Legacy',
            'due_date' => now()->addMonths(3)->toDateString(),
            'start_date' => now()->addMonth()->toDateString(),
            'start_time' => '08:00',
            'acceptance_criteria' => 'Legacy criterios',
        ]);

    $response->assertOk();
    $entrega->refresh();

    expect($entrega->description)->toBe('Descripción original');
    expect($entrega->acceptance_criteria)->toBeNull();
});
