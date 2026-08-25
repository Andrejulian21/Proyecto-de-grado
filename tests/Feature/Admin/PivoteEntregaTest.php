<?php

declare(strict_types=1);

use App\Actions\Entrega\StoreEntregaAction;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Phase 2 (issue #39): the entrega_proyecto pivot must be the single source
// of truth. After StoreEntregaAction — the only production creation path —
// every entrega is linked through the pivot and none depends on the legacy
// direct FK.

it('StoreEntregaAction leaves no entrega with a direct link lacking a pivot', function () {
    $semestre = Semestre::factory()->create(['is_active' => true]);
    Proyecto::factory(3)->create(['semester_id' => $semestre->id]);

    app(StoreEntregaAction::class)->handle([
        'grupo_id' => $semestre->id,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega verificacion',
        'descripcion' => 'Desc',
        'fecha_limite' => '2026-08-15',
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
        ],
    ]);

    // The exact verification query from the issue returns 0: no entrega has
    // a direct link that lacks a pivot row.
    $orphans = DB::table('entregas')
        ->whereNotNull('proyecto_id')
        ->whereNotIn('id', DB::table('entrega_proyecto')->select('entrega_id'))
        ->count();

    expect($orphans)->toBe(0);
});

it('every entrega created via StoreEntregaAction is pivot-linked to at least one project', function () {
    $semestre = Semestre::factory()->create(['is_active' => true]);
    Proyecto::factory(2)->create(['semester_id' => $semestre->id]);

    app(StoreEntregaAction::class)->handle([
        'grupo_id' => $semestre->id,
        'fase' => 'anteproyecto',
        'titulo' => 'Entrega pivot',
        'descripcion' => 'Desc',
        'fecha_limite' => '2026-08-15',
        'archivos_requeridos' => [
            ['id' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
        ],
    ]);

    $entrega = Entrega::sole();

    expect($entrega->proyectos()->count())->toBe(2);
    expect(DB::table('entrega_proyecto')->where('entrega_id', $entrega->id)->count())->toBe(2);
});