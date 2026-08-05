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
 * existing pair state that the endpoint validation must respect).
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

// -- RF-ENT-01: documento-proyecto default -----------------------------------

it('store crea entrega con documento-proyecto y grade_percentage valido', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, ['grade_percentage' => 60]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('60.00');
    $principal = collect($response->json('data.archivos_requeridos'))
        ->firstWhere('slug', 'documento-proyecto');
    expect($principal['analizable_ia'])->toBeFalse();
});

it('store rechaza payload sin archivo principal documento-proyecto (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'archivos_requeridos' => [
                ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false],
            ],
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.archivos_requeridos.0'))
        ->toContain("Debe existir al menos el archivo 'documento del proyecto'");
});

// -- RF-ENT-02: analizable_ia only on the main file --------------------------

it('store persiste analizable_ia en el archivo principal', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true, 'analizable_ia' => true],
                ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false],
            ],
        ]));

    $response->assertCreated();
    $principal = collect($response->json('data.archivos_requeridos'))
        ->firstWhere('slug', 'documento-proyecto');
    expect($principal['analizable_ia'])->toBeTrue();
});

it('store rechaza analizable_ia en archivo secundario (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
                ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false, 'analizable_ia' => true],
            ],
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.archivos_requeridos.0'))
        ->toContain("Solo el archivo 'documento del proyecto' puede ser analizable con IA");
});

// -- RF-ENT-03: grade_percentage range ---------------------------------------

it('store rechaza grade_percentage fuera de rango (422)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, ['grade_percentage' => 150]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))
        ->toContain('El porcentaje de nota debe estar entre 0 y 100');
});

// -- RF-ENT-04: pair weight rule on store ------------------------------------

it('store bloquea suma del par que superaria 100 (422)', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 70.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'presentacion_anteproyecto',
            'grade_percentage' => 40,
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('superaría el 100%');
});

it('store bloquea par completo que no suma exacto 100 (422)', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 50.0);
    seedEntregaConPeso($this->semestre->id, 'presentacion_anteproyecto', 40.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'presentacion_anteproyecto',
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

it('store permite completar el par en exacto 100', function () {
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 40.0);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', baseEntregaPayload($this->semestre->id, [
            'fase' => 'presentacion_anteproyecto',
            'grade_percentage' => 60,
        ]));

    $response->assertCreated();
    expect($response->json('data.grade_percentage'))->toBe('60.00');
});

// -- RF-ENT-04 / D4: pair weight rule on update -------------------------------

it('update cambia grade_percentage a NULL sin bloquear (D4)', function () {
    $a = seedEntregaConPeso($this->semestre->id, 'anteproyecto', 50.0);
    seedEntregaConPeso($this->semestre->id, 'presentacion_anteproyecto', 50.0);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => null]);

    $response->assertOk();
    $a->refresh();
    expect($a->grade_percentage)->toBeNull();
});

it('update bloquea suma del par que superaria 100 (422)', function () {
    $a = seedEntregaConPeso($this->semestre->id, 'anteproyecto', 70.0);
    seedEntregaConPeso($this->semestre->id, 'presentacion_anteproyecto', 20.0);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => 90]);

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('superaría el 100%');
});

it('update bloquea par completo que no sumaria exacto 100 (422)', function () {
    // Pair closed even when excluding the updated row: 40 (anteproyecto) +
    // 10 (presentacion_anteproyecto). Updating A 50 -> 30 would leave the
    // closed pair at 80 -> completeness rule (RF-ENT-04) must reject.
    $a = seedEntregaConPeso($this->semestre->id, 'anteproyecto', 50.0);
    seedEntregaConPeso($this->semestre->id, 'anteproyecto', 40.0);
    seedEntregaConPeso($this->semestre->id, 'presentacion_anteproyecto', 10.0);

    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/entregas/{$a->id}", ['grade_percentage' => 30]);

    $response->assertStatus(422);
    expect($response->json('errors.grade_percentage.0'))->toContain('exactamente 100%');
});
