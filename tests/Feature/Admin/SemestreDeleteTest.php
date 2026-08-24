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
});

it('coordinador puede eliminar semestre sin proyectos ni entregas vinculadas', function () {
    $semestre = Semestre::factory()->create();

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/semestres/{$semestre->id}");

    $response->assertOk();
    expect(Semestre::count())->toBe(0);
});

it('NO elimina semestre con proyectos vinculados — 422 y el semestre permanece', function () {
    $semestre = Semestre::factory()->create();
    Proyecto::factory()->create(['semester_id' => $semestre->id]);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/semestres/{$semestre->id}");

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'No se puede eliminar el grupo porque tiene proyectos vinculados.',
        ]);
    expect(Semestre::find($semestre->id))->not->toBeNull();
});

it('NO elimina semestre con entregas vinculadas — 422 y el semestre permanece', function () {
    $semestre = Semestre::factory()->create();
    Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega Anteproyecto',
        'description' => 'Descripción de la entrega',
        'due_date' => '2026-08-15',
        'status' => 'pendiente',
        'archivos_requeridos' => json_encode([
            ['slug' => 'documento', 'nombre' => 'Documento', 'versionamiento' => true],
        ]),
    ]);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/semestres/{$semestre->id}");

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'No se puede eliminar el grupo porque tiene entregas vinculadas.',
        ]);
    expect(Semestre::find($semestre->id))->not->toBeNull();
});