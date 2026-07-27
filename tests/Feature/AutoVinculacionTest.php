<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates pivots when project is created in a semester with existing entregas', function () {
    $semestre = Semestre::factory()->create(['is_active' => true]);

    // Create entregas first
    $entrega1 = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega Anteproyecto',
        'description' => 'Desc',
        'due_date' => '2026-08-15',
        'status' => 'pendiente',
        'archivos_requeridos' => json_encode([
            ['slug' => 'documento', 'nombre' => 'Documento', 'versionamiento' => true],
        ]),
    ]);

    $entrega2 = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Entrega Desarrollo',
        'description' => 'Desc',
        'due_date' => '2026-10-15',
        'status' => 'pendiente',
        'archivos_requeridos' => json_encode([
            ['slug' => 'documento', 'nombre' => 'Documento', 'versionamiento' => true],
        ]),
    ]);

    // Now create a project in the same semester
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
    ]);

    // The project should be auto-linked to both entregas
    expect($proyecto->entregasPivot()->count())->toBe(2);

    // Verify specific entregas are linked
    expect($proyecto->entregasPivot()->where('entrega_id', $entrega1->id)->exists())->toBeTrue();
    expect($proyecto->entregasPivot()->where('entrega_id', $entrega2->id)->exists())->toBeTrue();
});

it('does not link project to entregas from other semesters', function () {
    $semestre1 = Semestre::factory()->create(['is_active' => true]);
    $semestre2 = Semestre::factory()->create(['is_active' => true]);

    // Entrega in semestre 1
    $entrega = Entrega::create([
        'semester_id' => $semestre1->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega S1',
        'description' => 'Desc',
        'due_date' => '2026-08-15',
        'status' => 'pendiente',
        'archivos_requeridos' => json_encode([
            ['slug' => 'documento', 'nombre' => 'Documento', 'versionamiento' => true],
        ]),
    ]);

    // Project in semestre 2
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre2->id,
    ]);

    expect($proyecto->entregasPivot()->count())->toBe(0);
});
