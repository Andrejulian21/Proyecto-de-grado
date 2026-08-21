<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
    $this->proyecto->estudiantes()->attach($this->estudiante);
});

function payloadEntregaSinMetricas(int $semestreId, string $descripcion = 'En esta entrega el estudiante debe presentar el planteamiento del problema.'): array
{
    return [
        'grupo_id' => $semestreId,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega de planteamiento',
        'descripcion' => $descripcion,
        'fecha_limite' => '2026-09-15',
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ];
}

it('crea una entrega sin configurar métricas y persiste la descripción', function () {
    $descripcion = 'En esta entrega el estudiante debe presentar el planteamiento del problema, incluyendo contexto, situación problemática, causas y consecuencias.';

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadEntregaSinMetricas($this->semestre->id, $descripcion));

    $response->assertCreated();

    $entrega = Entrega::query()->first();
    expect($entrega)->not->toBeNull()
        ->and($entrega->description)->toBe($descripcion)
        ->and($entrega->evaluation_metrics)->toBeNull();
});

it('ignora metricas_evaluacion enviadas al crear y no las persiste', function () {
    $payload = payloadEntregaSinMetricas($this->semestre->id);
    $payload['metricas_evaluacion'] = 'Evaluar objetivos ABET y asignar nota por criterio.';
    $payload['evaluation_metrics'] = 'Rúbrica oculta';

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', $payload);

    $response->assertCreated();

    $entrega = Entrega::query()->first();
    expect($entrega->evaluation_metrics)->toBeNull()
        ->and($entrega->description)->toBe($payload['descripcion']);
});

it('no borra evaluation_metrics históricas al actualizar la descripción', function () {
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Título original',
        'description' => 'Descripción original',
        'due_date' => '2026-09-15',
        'status' => 'pendiente',
        'evaluation_metrics' => 'Claridad de objetivos y coherencia metodológica.',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ]);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$entrega->id}", [
            'descripcion' => 'Descripción actualizada para el estudiante.',
            'metricas_evaluacion' => 'Intento de sobrescribir métricas',
        ]);

    $response->assertOk();
    $entrega->refresh();

    expect($entrega->description)->toBe('Descripción actualizada para el estudiante.')
        ->and($entrega->evaluation_metrics)->toBe('Claridad de objetivos y coherencia metodológica.');
});

it('el listado de estudiante incluye descripcion y no metricas_evaluacion', function () {
    $descripcion = 'Debe entregar el planteamiento del problema.';

    $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadEntregaSinMetricas($this->semestre->id, $descripcion))
        ->assertCreated();

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();
    $item = collect($response->json('data'))->first();

    expect($item)->not->toBeNull()
        ->and($item['descripcion'])->toBe($descripcion)
        ->and(array_key_exists('metricas_evaluacion', $item))->toBeFalse();
});
