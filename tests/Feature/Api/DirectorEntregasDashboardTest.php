<?php

declare(strict_types=1);

use App\Actions\Entrega\SolicitarEntregaAction;
use App\Enums\EstadoEntrega;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Aislamiento de la card "Entregas pendientes por revisar" del panel del
 * director (GET /api/director/entregas).
 *
 * Una fila de `entregas` es una PLANTILLA compartida por todos los proyectos
 * del semestre: varios proyectos apuntan a la MISMA entrega mediante el pivote
 * `entrega_proyecto`. Por eso el listado NO puede derivarse de
 * `entregas.status` (estado global de la plantilla) ni de "el primer proyecto
 * enlazado" (primer PK, no necesariamente del director): ambos leen estado y
 * datos de proyectos ajenos.
 *
 * Estos tests fijan las dos reglas de la card:
 *   A) los datos mostrados son del proyecto que supervisa el director;
 *   B) "pendiente para este director" = alguno de SUS proyectos tiene al menos
 *      una versión subida y ese pivote todavía no fue calificado.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->semestre = Semestre::factory()->create(['is_active' => true]);

    $this->directorA = User::factory()->director()->create(['name' => 'Director Alfa']);
    $this->directorB = User::factory()->director()->create(['name' => 'Director Bravo']);

    $this->estudianteA = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'name' => 'Estudiante Alfa',
    ]);
    $this->estudianteB = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'name' => 'Estudiante Bravo',
    ]);

    // P2 se crea ANTES que P1 a propósito: su PK es menor, así que cualquier
    // "primer proyecto enlazado" sin restringir elige al proyecto ajeno.
    $this->proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->directorB->id,
        'code' => 'PG-BRAVO',
        'title' => 'Proyecto Bravo',
    ]);
    $this->proyectoB->estudiantes()->attach($this->estudianteB);

    $this->proyectoA = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->directorA->id,
        'code' => 'PG-ALFA',
        'title' => 'Proyecto Alfa',
    ]);
    $this->proyectoA->estudiantes()->attach($this->estudianteA);

    // UNA sola plantilla compartida por los dos proyectos.
    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto del semestre',
        'description' => 'Plantilla compartida por todos los proyectos del semestre.',
        'start_date' => now()->subDay()->toDateString(),
        'due_date' => now()->addMonth()->toDateString(),
        'status' => EstadoEntrega::Creada->value,
        'grade_percentage' => 20,
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto'],
        ],
    ]);

    foreach ([$this->proyectoB, $this->proyectoA] as $proyecto) {
        if (! $this->entrega->proyectos()->where('proyecto_id', $proyecto->id)->exists()) {
            $this->entrega->proyectos()->attach($proyecto->id);
        }
    }

    $this->pivotA = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyectoA->id)
        ->firstOrFail();

    $this->pivotB = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyectoB->id)
        ->firstOrFail();
});

/**
 * Sube una versión al pivote del proyecto indicado.
 *
 * Refleja EntregaEstudianteController: al subir una versión la entrega pasa a
 * 'enviada' — un estado GLOBAL de la plantilla, no del proyecto.
 */
function subirVersion(EntregaProyecto $pivot, string $nombre): VersionDocumento
{
    $version = VersionDocumento::create([
        'entrega_id' => $pivot->entrega_id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'version_number' => 1,
        'file_path' => "entregas/{$pivot->entrega_id}/{$nombre}",
        'file_size' => 1024,
        'original_name' => $nombre,
        'uploaded_at' => now(),
    ]);

    $pivot->entrega->update(['status' => EstadoEntrega::Enviada->value]);

    return $version;
}

/**
 * (A) Cuando la entrega está pendiente para el director A, la fila mostrada
 * debe ser la de SU proyecto (P1), nunca la del proyecto ajeno (P2) aunque P2
 * tenga un PK menor en `proyectos`.
 */
it('muestra los datos del proyecto que supervisa el director y no los de otro', function () {
    subirVersion($this->pivotA, 'alfa.docx');

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/director/entregas');

    $response->assertOk();

    $data = collect($response->json('data'));

    expect($data)->toHaveCount(1);

    $fila = $data->first();

    expect($fila['id'])->toBe($this->entrega->id)
        ->and($fila['proyecto_id'])->toBe($this->proyectoA->id)
        ->and($fila['codigo'])->toBe($this->proyectoA->code)
        ->and($fila['proyecto'])->toBe('Proyecto Alfa')
        ->and($fila['estudiante'])->toBe('Estudiante Alfa');

    // Ningún dato del proyecto del otro director puede aparecer en el payload.
    expect($response->getContent())
        ->not->toContain($this->proyectoB->code)
        ->not->toContain('Proyecto Bravo')
        ->not->toContain('Estudiante Bravo');
});

/**
 * (B) El estudiante de P2 solicita habilitación y sube su versión: la entrega
 * queda 'enviada' a nivel plantilla, pero A no tiene NADA que revisar en ella.
 * La card de A no debe listarla.
 */
it('no lista la entrega cuando la única version subida es de otro director', function () {
    // Flujo real del estudiante: solicitar habilitación sobre la plantilla.
    app(SolicitarEntregaAction::class)->handle(
        $this->entrega,
        $this->estudianteB->id,
        '127.0.0.1',
        'pest',
    );

    expect($this->entrega->fresh()->status)->toBe(EstadoEntrega::Solicitada);

    subirVersion($this->pivotB, 'bravo.docx');

    expect($this->entrega->fresh()->status)->toBe(EstadoEntrega::Enviada);

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/director/entregas');

    $response->assertOk();

    expect(collect($response->json('data')))->toBeEmpty();
});

/**
 * (B, positivo) Cuando A sube una versión en SU proyecto, la entrega vuelve a
 * listarse — y con los datos de P1.
 */
it('lista la entrega cuando el propio director tiene una version sin calificar', function () {
    subirVersion($this->pivotA, 'alfa.docx');

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/director/entregas');

    $response->assertOk();

    $fila = collect($response->json('data'))->firstWhere('id', $this->entrega->id);

    expect($fila)->not->toBeNull()
        ->and($fila['proyecto_id'])->toBe($this->proyectoA->id)
        ->and($fila['codigo'])->toBe($this->proyectoA->code)
        ->and($fila['estudiante'])->toBe('Estudiante Alfa');
});

/**
 * (B, cierre) Al calificar el pivote de SU proyecto, la entrega deja de estar
 * pendiente para ese director aunque la plantilla siga 'enviada' por el otro.
 */
it('deja de listar la entrega cuando el director califica el pivote de su proyecto', function () {
    subirVersion($this->pivotB, 'bravo.docx');
    subirVersion($this->pivotA, 'alfa.docx');

    $antes = $this->actingAs($this->directorA)->getJson('/api/director/entregas');
    $antes->assertOk();
    expect(collect($antes->json('data')))->toHaveCount(1);

    $this->pivotA->update(['director_grade' => 4.5, 'estado' => EstadoEntrega::Aprobada->value]);

    $despues = $this->actingAs($this->directorA)->getJson('/api/director/entregas');

    $despues->assertOk();
    expect(collect($despues->json('data')))->toBeEmpty();

    // La calificación es por proyecto: el otro director sigue con lo suyo.
    $otro = $this->actingAs($this->directorB)->getJson('/api/director/entregas');
    $otro->assertOk();
    expect(collect($otro->json('data')))->toHaveCount(1);
});

/**
 * Una entrega 'enviada' sin ninguna versión no es pendiente de nadie: la card
 * no debe inventar filas ni romperse.
 */
it('no lista una entrega enviada que no tiene versiones y no falla con el pivote inconsistente', function () {
    $this->entrega->update(['status' => EstadoEntrega::Enviada->value]);

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/director/entregas');

    $response->assertOk();

    expect(collect($response->json('data')))->toBeEmpty();

    // Pivote huérfano: la plantilla apunta a un proyecto borrado. La card debe
    // seguir respondiendo 200 con una lista válida, sin datos inventados.
    Proyecto::query()->delete();

    $huerfano = $this->actingAs($this->directorA)->getJson('/api/director/entregas');

    $huerfano->assertOk();
    expect(collect($huerfano->json('data')))->toBeEmpty();
});

/**
 * Un director sin proyectos en semestres activos recibe una lista vacía, sin
 * error: el filtro no puede desaparecer cuando el conjunto de IDs es vacío.
 */
it('devuelve una lista vacia para un director sin proyectos', function () {
    subirVersion($this->pivotB, 'bravo.docx');

    $response = $this->actingAs($this->directorA)
        ->getJson('/api/director/entregas');

    // El director A sí tiene P1: la plantilla no se lista porque P1 no subió nada.
    $response->assertOk();

    $sinProyectos = User::factory()->director()->create();

    $vacio = $this->actingAs($sinProyectos)
        ->getJson('/api/director/entregas');

    $vacio->assertOk();
    expect(collect($vacio->json('data')))->toBeEmpty();
});
