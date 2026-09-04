<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Issue #53: no existía ningún test de conteo de consultas para el
 * seguimiento. Este guardián fija que GET /api/admin/seguimiento/semestre/{id}
 * no ejecute consultas N+1 por proyecto ni por entrega.
 *
 * Escala del issue: 200 proyectos/semestre × 8 entregas. Aquí usamos una
 * escala reducida (10 proyectos × 8 entregas) que ya hace explotar el
 * conteo si reaparece el patrón de consultas en bucle (~120 consultas
 * antes de la corrección; ~9 después con eager loading).
 */
it('seguimiento por semestre no ejecuta consultas proporcionales a proyectos/entregas', function () {
    $coordinador = User::factory()->coordinador()->create();
    $semestre = Semestre::factory()->create(['is_active' => true]);

    $fases = ['anteproyecto', 'presentacion_anteproyecto', 'desarrollo', 'presentacion_final'];

    foreach (range(1, 10) as $i) {
        $proyecto = Proyecto::factory()->create(['semester_id' => $semestre->id]);
        $proyecto->estudiantes()->attach(
            User::factory()->create(['role' => UserRole::Estudiante->value])
        );

        // 8 entregas por proyecto (escala del issue).
        foreach (range(1, 8) as $j) {
            $entrega = Entrega::create([
                'semester_id' => $semestre->id,
                'phase' => $fases[($j - 1) % 4],
                'title' => "Entrega {$proyecto->code}-{$j}",
                'due_date' => '2026-12-01',
                'status' => 'pendiente',
            ]);
            $entrega->proyectos()->attach($proyecto->id);
        }

        // Semanas distintas: existe un UNIQUE (proyecto_id, semana).
        foreach ([1, 2, 3] as $semana) {
            Bitacora::factory()->create([
                'proyecto_id' => $proyecto->id,
                'semana' => $semana,
            ]);
        }
    }

    DB::enableQueryLog();

    $this->actingAs($coordinador)
        ->getJson("/api/admin/seguimiento/semestre/{$semestre->id}")
        ->assertOk();

    $queries = count(DB::getQueryLog());

    // Eager loads: semestre + proyectos + entregasPivot + versiones +
    // entregaProyecto + bitacoras + estudiantes + director + observaciones.
    // Un N+1 con 10 proyectos × 8 entregas superaría ampliamente este umbral.
    expect($queries)->toBeLessThanOrEqual(20);
});

it('devuelve estados y conteos correctos tras el refactor', function () {
    $coordinador = User::factory()->coordinador()->create();
    $semestre = Semestre::factory()->create(['is_active' => true]);

    $proyecto = Proyecto::factory()->create(['semester_id' => $semestre->id]);
    $proyecto->estudiantes()->attach(
        User::factory()->create(['role' => UserRole::Estudiante->value])
    );

    // Entrega entregada: versión legacy sin entrega_proyecto_id (fallback).
    $entregada = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance 1',
        'due_date' => now()->subWeek()->toDateString(),
        'status' => 'enviada',
    ]);
    $entregada->proyectos()->attach($proyecto->id);
    VersionDocumento::create([
        'entrega_id' => $entregada->id,
        'version_number' => 1,
        'file_path' => '/tmp/avance1.pdf',
        'file_size' => 1024,
        'original_name' => 'avance1.pdf',
        'uploaded_at' => now(),
    ]);

    // Entrega pendiente: fecha futura, sin versión.
    $pendiente = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance 2',
        'due_date' => now()->addWeek()->toDateString(),
        'status' => 'pendiente',
    ]);
    $pendiente->proyectos()->attach($proyecto->id);

    Bitacora::factory()->create(['proyecto_id' => $proyecto->id, 'semana' => 3]);
    Bitacora::factory()->create(['proyecto_id' => $proyecto->id, 'semana' => 20]);

    $response = $this->actingAs($coordinador)
        ->getJson("/api/admin/seguimiento/semestre/{$semestre->id}")
        ->assertOk();

    $proyectoData = collect($response->json('data.proyectos'))->firstWhere('id', $proyecto->id);

    expect($proyectoData)->not->toBeNull();
    expect($proyectoData['bitacoras_grupo_a'])->toBe(1);
    expect($proyectoData['bitacoras_grupo_b'])->toBe(1);

    $estados = collect($proyectoData['fases'])
        ->flatMap(fn ($fase) => collect($fase['entregas'])->pluck('estado'))
        ->all();

    expect($estados)->toContain('entregada');
    expect($estados)->toContain('pendiente');
});
