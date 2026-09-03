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
 * Base payload for POST /api/admin/entregas. The main file slug is
 * always 'documento-proyecto' (RF-ENT-01).
 */
function baseEntregaPayload(int $semestreId, array $overrides = []): array
{
    return array_merge([
        'grupo_id' => $semestreId,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega Test',
        'descripcion' => 'Descripción de la entrega',
        'fecha_limite' => now()->addMonths(2)->toDateString(),
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ], $overrides);
}

/**
 * Seed an entrega with a grade_percentage directly (used to set up the
 * existing phase state that the endpoint validation must respect).
 */
function seedEntregaConPeso(int $semestreId, string $phase, ?float $peso): Entrega
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

it('store crea entrega con documento-proyecto y grade_percentage valido', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, ['grade_percentage' => 60]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('60.00');
    $principal = collect($response->json('data.archivos_requeridos'))
        ->firstWhere('slug', 'documento-proyecto');
    expect($principal['analizable_ia'])->toBeFalse();
});

it('store acepta documentos sin slug documento-proyecto', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'archivos_requeridos' => [
                ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false],
            ],
        ]));

    $response->assertCreated();
    expect($response->json('data.archivos_requeridos.0.slug'))->toBe('anexo');
});

it('store persiste analizable_ia en cualquier documento si es el unico', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
                ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false, 'analizable_ia' => true],
            ],
        ]));

    $response->assertCreated();
    $anexo = collect($response->json('data.archivos_requeridos'))->firstWhere('slug', 'anexo');
    expect($anexo['analizable_ia'])->toBeTrue();
});

it('store rechaza dos documentos analizable_ia (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true, 'analizable_ia' => true],
                ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false, 'analizable_ia' => true],
            ],
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.archivos_requeridos.0'))
        ->toContain('Solo un documento de la entrega puede analizarse con IA');
});

// -- RF-ENT-03: grade_percentage range ---------------------------------------

it('store rechaza grade_percentage fuera de rango (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, ['grade_percentage' => 150]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))
        ->toContain('El porcentaje de nota debe estar entre 0 y 100');
});

// -- RF-ENT-04: phase weight rule on store -----------------------------------
// Each phase (anteproyecto, desarrollo) independently sums to 100%.
// Presentación phases do NOT participate in the grade_percentage system.

it('store bloquea suma de anteproyecto que no suma exacto 100 (422)', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 70.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 40,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('exactamente 100%');
});

it('store bloquea anteproyecto completo que no suma exacto 100 (422)', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 50.0);
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 40.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 20,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('exactamente 100%');
});

it('store permite grade_percentage NULL sin bloquear', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, ['grade_percentage' => null]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBeNull();
});

it('store permite completar anteproyecto en exacto 100', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 40.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'anteproyecto',
            'grade_percentage' => 60,
        ]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('60.00');
});

it('store bloquea suma de desarrollo que no suma exacto 100 (422)', function () {
    seedEntregaConPeso($this->semestre->id, 'desarrollo', 70.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'desarrollo',
            'grade_percentage' => 40,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('exactamente 100%');
});

it('store rechaza grade_percentage en presentacion_anteproyecto (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'presentacion_anteproyecto',
            'grade_percentage' => 40,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('no aplica en fases de presentación');
});

it('store rechaza grade_percentage en presentacion_final (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'presentacion_final',
            'grade_percentage' => 90,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('no aplica en fases de presentación');
});

// -- RF-ENT-04: phase weight rule on update ----------------------------------

it('update cambia grade_percentage a NULL sin bloquear', function () {
    $a = seedEntregaConPeso($this->semestre->id, 'anteproyecto', 50.0);
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 50.0);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => null]);

    $response->assertOk();
    $a->refresh();
    expect($a->grade_percentage)->toBeNull();
});

it('update permite cambiar grade_percentage libremente sin validacion de suma', function () {
    $a = seedEntregaConPeso($this->semestre->id, 'anteproyecto', 70.0);
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 20.0);

    // Coordinator can adjust freely — no backend restriction on update
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => 90]);

    $response->assertOk();
});

it('update permite cambiar anteproyecto sin considerar presentacion', function () {
    $a = seedEntregaConPeso($this->semestre->id, 'anteproyecto', 70.0);
    seedEntregaConPeso($this->semestre->id, 'presentacion_anteproyecto', 20.0);

    // presentacion_anteproyecto does NOT participate — updating $a from 70→90
    // excludes $a itself, so sum = 90 (only $a's new value). Valid.
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => 90]);

    $response->assertOk();
});

it('update permite cambiar desarrollo sin considerar presentacion_final', function () {
    $a = seedEntregaConPeso($this->semestre->id, 'desarrollo', 70.0);
    seedEntregaConPeso($this->semestre->id, 'presentacion_final', 20.0);

    // presentacion_final does NOT participate — updating $a from 70→50
    // excludes $a itself, so sum = 50. Valid.
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => 50]);

    $response->assertOk();
});

it('store permite desarrollo independiente de anteproyecto', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 100.0);

    // desarrollo is independent — summing to 100 in its own phase
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'desarrollo',
            'grade_percentage' => 100,
        ]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('100.00');
});
