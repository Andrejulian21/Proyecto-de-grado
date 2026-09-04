<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Issue #40: no existía ningún test de conteo de consultas. Este guardián
 * fija que GET /api/admin/entregas no ejecute consultas N+1 por entrega:
 * con 25 entregas el total debe quedar plano (eager loads), no lineal.
 */
it('listar entregas no ejecuta consultas N+1 por entrega', function () {
    $coordinador = User::factory()->coordinador()->create();
    $semestre = Semestre::factory()->create(['is_active' => true]);
    $proyecto = Proyecto::factory()->create(['semester_id' => $semestre->id]);
    $proyecto->estudiantes()->attach(
        User::factory()->create(['role' => UserRole::Estudiante->value])
    );

    collect(range(1, 25))->each(function () use ($semestre, $proyecto) {
        $entrega = Entrega::create([
            'semester_id' => $semestre->id,
            'phase' => 'anteproyecto',
            'title' => 'Entrega',
            'due_date' => '2026-12-01',
            'status' => 'pendiente',
        ]);
        $entrega->proyectos()->attach($proyecto->id);
    });

    DB::enableQueryLog();

    $this->actingAs($coordinador)
        ->getJson('/api/admin/entregas')
        ->assertOk();

    $queries = count(DB::getQueryLog());

    // Eager loads (entregas + semestre + proyecto + proyecto.semestre +
    // proyectos) mantienen el conteo plano. Un N+1 con 25 entregas
    // superaría ampliamente este umbral.
    expect($queries)->toBeLessThanOrEqual(20);
});
