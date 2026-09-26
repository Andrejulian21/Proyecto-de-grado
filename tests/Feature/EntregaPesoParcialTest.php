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
 * Base payload for POST /api/admin/entregas (partial-weight suite).
 */
function parcialEntregaPayload(int $semestreId, array $overrides = []): array
{
    return array_merge([
        'grupo_id' => $semestreId,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega Parcial Test',
        'descripcion' => 'Descripción de la entrega',
        'fecha_limite' => now()->addMonths(2)->toDateString(),
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ], $overrides);
}

/**
 * Seed an entrega with a grade_percentage directly (partial-weight suite).
 */
function seedParcialEntregaConPeso(int $semestreId, string $phase, ?float $peso): Entrega
{
    return Entrega::create([
        'semester_id' => $semestreId,
        'phase' => $phase,
        'title' => 'Entrega '.$phase,
        'description' => 'x',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'pendiente',
        'grade_percentage' => $peso,
    ]);
}

it('store acepta segunda entrega parcial 60+25=85 sin exigir 100 exacto', function () {
    seedParcialEntregaConPeso($this->semestre->id, 'anteproyecto', 60.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', parcialEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 25,
        ]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('25.00');
});

it('store rechaza segunda entrega que supera 100 (60+50=110)', function () {
    seedParcialEntregaConPeso($this->semestre->id, 'anteproyecto', 60.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', parcialEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 50,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('superaría el 100%');
});

it('store acepta suma exacta 100 (40+60)', function () {
    seedParcialEntregaConPeso($this->semestre->id, 'anteproyecto', 40.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', parcialEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 60,
        ]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('60.00');
});

it('store acepta secuencia triangulada 60+25+15=100', function () {
    seedParcialEntregaConPeso($this->semestre->id, 'anteproyecto', 60.0);

    $segunda = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', parcialEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 25,
        ]));
    $segunda->assertCreated();

    $tercera = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', parcialEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 15,
        ]));
    $tercera->assertCreated();
    expect($tercera->json('data.grade_percentage'))->toBe('15.00');
});
