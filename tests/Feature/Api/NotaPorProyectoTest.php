<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * La nota vive en el proyecto, no en la entrega.
 *
 * Una `entrega` es una PLANTILLA compartida por todos los proyectos del
 * semestre. Desde el fix `0c96202` la calificación se escribe en el pivote
 * `entrega_proyecto` (`director_grade`, `estado`, `observaciones_director`),
 * porque `entregas.status` / `consolidated_grade` / `evaluation_complete`
 * describen a la plantilla entera y calificar un proyecto pisaba el estado de
 * todos los demás.
 *
 * Estos tests fijan el contrato de LECTURA que hace falta para que ningún rol
 * se quede ciego: el estudiante ve la nota de SU proyecto (nunca la del
 * semestre ni un promedio), y cuando el pivote todavía no tiene nota se cae
 * al valor legacy `entregas.consolidated_grade` — en producción hay notas
 * escritas ahí antes del fix y no se pueden perder ni mostrarse vacías.
 *
 * Cero llamadas reales a Gemini.
 */
beforeEach(function () {
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->director = User::factory()->director()->create();
    $this->coordinador = User::factory()->coordinador()->create();

    $this->estudianteA = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->estudianteSinProyecto = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyectoA = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto A',
    ]);
    $this->proyectoA->estudiantes()->attach($this->estudianteA);

    $this->proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto B',
    ]);
    $this->proyectoB->estudiantes()->attach($this->estudianteB);

    // UNA plantilla compartida por los dos proyectos. La fila `entregas` NO
    // tiene nota: desde el fix la nota vive en cada pivote.
    $this->entrega = crearEntregaCompartida($this->semestre, 'Anteproyecto compartido');
    $this->entrega->proyectos()->attach($this->proyectoA->id);
    $this->entrega->proyectos()->attach($this->proyectoB->id);

    $this->pivotA = pivotDe($this->entrega, $this->proyectoA);
    $this->pivotB = pivotDe($this->entrega, $this->proyectoB);

    $this->pivotA->update(['director_grade' => 3.0, 'estado' => 'aprobada']);
    $this->pivotB->update(['director_grade' => 4.5, 'estado' => 'aprobada']);
});

function crearEntregaCompartida(Semestre $semestre, string $titulo): Entrega
{
    return Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => $titulo,
        'description' => 'Descripción de la entrega compartida',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => 'enviada',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento', 'versionamiento' => true],
        ],
    ]);
}

function pivotDe(Entrega $entrega, Proyecto $proyecto): EntregaProyecto
{
    return EntregaProyecto::where('entrega_id', $entrega->id)
        ->where('proyecto_id', $proyecto->id)
        ->firstOrFail();
}

function crearVersion(Entrega $entrega, EntregaProyecto $pivot, int $numero): VersionDocumento
{
    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'version_number' => $numero,
        'file_path' => "entregas/{$entrega->id}/v{$numero}.docx",
        'file_size' => 1024,
        'original_name' => "v{$numero}.docx",
        'archivo_requerido_id' => 'documento-proyecto',
        'uploaded_at' => now(),
    ]);
}

function entregaEnPayload(array $payload, string $titulo): ?array
{
    foreach ($payload['data'] ?? [] as $entrega) {
        if (($entrega['titulo'] ?? null) === $titulo) {
            return $entrega;
        }
    }

    return null;
}

it('el estudiante ve la nota de SU proyecto en la entrega compartida', function () {
    $titulo = $this->entrega->title;

    $payloadA = $this->actingAs($this->estudianteA)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();
    $payloadB = $this->actingAs($this->estudianteB)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();

    $entregaA = entregaEnPayload($payloadA, $titulo);
    $entregaB = entregaEnPayload($payloadB, $titulo);

    expect($entregaA)->not->toBeNull()
        ->and($entregaB)->not->toBeNull();

    // Cada uno ve SU nota: ni la del otro, ni un promedio de las dos.
    expect((float) $entregaA['nota'])->toBe(3.0)
        ->and((float) $entregaB['nota'])->toBe(4.5);
});

it('el estado de la entrega se resuelve por proyecto y no por la plantilla', function () {
    $titulo = $this->entrega->title;

    // B todavía no fue revisado: su pivote no tiene veredicto.
    $this->pivotB->update(['estado' => null, 'director_grade' => null]);

    $payloadA = $this->actingAs($this->estudianteA)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();
    $payloadB = $this->actingAs($this->estudianteB)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();

    expect(entregaEnPayload($payloadA, $titulo)['estado'])->toBe('aprobada');

    // Sin veredicto propio NO se hereda el estado de la plantilla: B no subió
    // nada a SU pivote, así que su entrega está 'pendiente'. Heredar 'enviada'
    // reportaba como entregada una entrega que este proyecto nunca hizo, solo
    // porque la plantilla la tenía en ese estado. Ver NotaEntregaResolver::estado.
    expect(entregaEnPayload($payloadB, $titulo)['estado'])->toBe('pendiente');
});

it('sin nota en el pivote cae al valor legacy consolidated_grade', function () {
    // Una entrega cargada ANTES del fix: la nota quedó en la fila plantilla.
    $legacy = crearEntregaCompartida($this->semestre, 'Entrega legacy');
    $legacy->proyectos()->attach($this->proyectoA->id);
    $legacy->update(['consolidated_grade' => 4.0]);

    $pivotLegacy = pivotDe($legacy, $this->proyectoA);
    $pivotLegacy->update(['estado' => 'aprobada', 'director_grade' => null]);

    $payload = $this->actingAs($this->estudianteA)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();

    $entregaLegacy = entregaEnPayload($payload, 'Entrega legacy');

    expect($entregaLegacy)->not->toBeNull()
        ->and((float) $entregaLegacy['nota'])->toBe(4.0);
});

it('el pivote gana sobre el valor legacy cuando ambos existen', function () {
    $this->entrega->update(['consolidated_grade' => 1.0]);

    $payload = $this->actingAs($this->estudianteA)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();

    expect((float) entregaEnPayload($payload, $this->entrega->title)['nota'])->toBe(3.0);
});

it('tras una revisión por proyecto el estudiante sigue viendo su nota', function () {
    // La revisión por proyecto (`?proyecto=`) es la que dejó de escribir en
    // `entregas.consolidated_grade`. Este es el test de la regresión.
    $this->pivotA->update(['director_grade' => null, 'estado' => null]);
    $version = crearVersion($this->entrega, $this->pivotA, 1);

    $this->actingAs($this->director)
        ->putJson("/api/admin/entregas/{$this->entrega->id}/revisar?proyecto={$this->proyectoA->id}", [
            'status' => 'aprobada',
            'director_grade' => 3.7,
            'version_id' => $version->id,
            'director_notes' => 'Buen avance',
        ])->assertOk();

    expect((float) $this->pivotA->fresh()->director_grade)->toBe(3.7);

    $payload = $this->actingAs($this->estudianteA)
        ->getJson('/api/estudiante/entregas')->assertOk()->json();

    expect((float) entregaEnPayload($payload, $this->entrega->title)['nota'])->toBe(3.7);
});

it('el reporte consolidado agrega las notas de los pivotes del proyecto', function () {
    // Segunda entrega del proyecto A para que el consolidado tenga dos notas.
    $otra = crearEntregaCompartida($this->semestre, 'Informe de avance');
    $otra->proyectos()->attach($this->proyectoA->id);
    pivotDe($otra, $this->proyectoA)->update([
        'director_grade' => 4.5,
        'estado' => 'aprobada',
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/reportes/consolidado?proyecto_id='.$this->proyectoA->id)
        ->assertOk();

    $entregas = collect($response->json('data.entregas'));

    expect((float) $entregas->firstWhere('title', 'Anteproyecto compartido')['nota'])->toBe(3.0)
        ->and((float) $entregas->firstWhere('title', 'Informe de avance')['nota'])->toBe(4.5)
        // (3.0 + 4.5) / 2
        ->and((float) $response->json('data.promedio_notas'))->toBe(3.75);
});

it('un estudiante sin proyecto no ve notas de nadie', function () {
    $this->actingAs($this->estudianteSinProyecto)
        ->getJson('/api/estudiante/entregas')
        ->assertStatus(404)
        ->assertJson(['error' => 'No tienes un proyecto asignado.']);
});
