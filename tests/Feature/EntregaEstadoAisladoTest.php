<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\Entregas\NotaEntregaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A delivery verdict must never be inherited from the shared template.
 *
 * An `entrega` is a semester-wide TEMPLATE: `entregas.status`,
 * `entregas.consolidated_grade` and `entregas.evaluation_complete` describe the
 * template, not the delivery of any one project. The per-project verdict lives
 * in the `entrega_proyecto` pivot, so reading the template's status as a
 * fallback answers a DIFFERENT project's question.
 *
 * Observed in production: a student whose project never submitted saw delivery 2
 * as "Enviada" because ANOTHER project had submitted it, while the director
 * supervision views (which scope correctly) showed the truth.
 *
 * The gate leak is worse than the display leak: `estaAprobada()` fed by the
 * template returned TRUE for EVERY project, so `bloqueaAvanceDeFase()` reported
 * the phase as unlocked for projects that had no verdict at all.
 *
 * GRADES keep the template fallback on purpose. `nota()` is asserted below so
 * this fix can never be mistaken for permission to remove it: grades were
 * recorded in `entregas.consolidated_grade` before the pivot existed, and
 * without the fallback they would vanish from student screens and reports. A
 * template verdict is another project's outcome; a template grade is a
 * historical grade.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = app(NotaEntregaResolver::class);

    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->director = User::factory()->director()->create();

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyectoA = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto A',
    ]);
    $this->proyectoA->estudiantes()->attach($this->estudiante);

    $this->proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto B',
    ]);
    $this->proyectoB->estudiantes()->attach($this->estudiante);
});

/**
 * The shared template, with the project links already in place.
 */
function entregaAisladaContexto(object $test, string $statusTemplate): Entrega
{
    $entrega = Entrega::create([
        'semester_id' => $test->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto compartido',
        'description' => 'El estudiante debe presentar el planteamiento del problema con contexto y justificación.',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => $statusTemplate,
    ]);

    $entrega->proyectos()->attach([$test->proyectoA->id, $test->proyectoB->id]);

    return $entrega;
}

function entregaAisladaPivot(Entrega $entrega, Proyecto $proyecto): EntregaProyecto
{
    return EntregaProyecto::where('entrega_id', $entrega->id)
        ->where('proyecto_id', $proyecto->id)
        ->firstOrFail();
}

function entregaAisladaVersion(Entrega $entrega, EntregaProyecto $pivot): VersionDocumento
{
    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'version_number' => 1,
        'file_path' => 'entregas/avance.pdf',
        'file_size' => 1024,
        'original_name' => 'avance.pdf',
        'uploaded_at' => now(),
    ]);
}

/**
 * `estado` as the student screen reports it.
 */
function entregaAisladaEstadoEnEndpoint(object $test, Entrega $entrega): mixed
{
    $response = $test->actingAs($test->estudiante)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();

    return collect($response->json('data'))->firstWhere('id', $entrega->id)['estado'];
}

describe('Aislamiento del estado de entrega por proyecto', function () {

    /**
     * (1) The exact production bug: another project's submission must not show
     * up as THIS project's delivery.
     */
    it('no hereda enviada de la plantilla cuando el proyecto no tiene veredicto ni versiones', function () {
        $entrega = entregaAisladaContexto($this, 'enviada');
        $pivot = entregaAisladaPivot($entrega, $this->proyectoA);

        expect($pivot->estado)->toBeNull()
            ->and(VersionDocumento::where('entrega_proyecto_id', $pivot->id)->exists())->toBeFalse();

        expect($this->resolver->estado($entrega, $pivot))->toBe('pendiente');
        expect(entregaAisladaEstadoEnEndpoint($this, $entrega))->toBe('pendiente');
    });

    /**
     * (2) Same leak through the terminal template status, which is the one that
     * unlocks the phase gate.
     */
    it('no hereda aprobada de la plantilla cuando el proyecto no tiene veredicto ni versiones', function () {
        $entrega = entregaAisladaContexto($this, 'aprobada');
        $pivot = entregaAisladaPivot($entrega, $this->proyectoA);

        expect($this->resolver->estado($entrega, $pivot))->toBe('pendiente');
        expect(entregaAisladaEstadoEnEndpoint($this, $entrega))->toBe('pendiente');
    });

    /**
     * (3) Submission is per project: the version row on THIS project's own
     * pivot is the only evidence that THIS project submitted THIS delivery.
     */
    it('reporta enviada cuando este proyecto tiene una version en su propio pivote', function () {
        $entrega = entregaAisladaContexto($this, 'pendiente');
        $pivotA = entregaAisladaPivot($entrega, $this->proyectoA);

        entregaAisladaVersion($entrega, $pivotA);

        expect($this->resolver->estado($entrega, $pivotA))->toBe('enviada');
        expect(entregaAisladaEstadoEnEndpoint($this, $entrega))->toBe('enviada');
    });

    /**
     * (4) The pivot verdict is authoritative regardless of the template.
     */
    it('devuelve el veredicto del pivote cuando existe, ignorando la plantilla', function () {
        $entrega = entregaAisladaContexto($this, 'pendiente');
        $pivotA = entregaAisladaPivot($entrega, $this->proyectoA);

        $pivotA->update(['estado' => 'aprobada']);

        expect($this->resolver->estado($entrega->refresh(), $pivotA->refresh()))->toBe('aprobada');
        expect(entregaAisladaEstadoEnEndpoint($this, $entrega))->toBe('aprobada');
    });

    /**
     * (5) A rejection is a verdict too: it must survive verbatim, and it must
     * NOT be laundered into 'enviada' by the presence of a version.
     */
    it('devuelve rechazada cuando el pivote registra un veredicto de rechazo', function () {
        $entrega = entregaAisladaContexto($this, 'aprobada');
        $pivotA = entregaAisladaPivot($entrega, $this->proyectoA);

        $pivotA->update(['estado' => 'rechazada']);
        entregaAisladaVersion($entrega, $pivotA);

        expect($this->resolver->estado($entrega->refresh(), $pivotA->refresh()))->toBe('rechazada');
        expect(entregaAisladaEstadoEnEndpoint($this, $entrega))->toBe('rechazada');
    });

    /**
     * (6) The gate leak. An approved TEMPLATE must not unlock the phase of a
     * project that never got its own verdict.
     */
    it('el gate de fase no se abre por una plantilla aprobada para otro proyecto', function () {
        $entrega = entregaAisladaContexto($this, 'aprobada');

        $pivotA = entregaAisladaPivot($entrega, $this->proyectoA);
        $pivotB = entregaAisladaPivot($entrega, $this->proyectoB);

        // Project A earned its approval on its own pivot.
        $pivotA->update(['estado' => 'aprobada']);

        // Project B never got a verdict and never uploaded anything.
        expect($pivotB->refresh()->estado)->toBeNull();
        expect(VersionDocumento::where('entrega_proyecto_id', $pivotB->id)->exists())->toBeFalse();

        // B must stay blocked: an approved template is not its approval.
        expect($this->resolver->estaAprobada($entrega, $pivotB->refresh()))->toBeFalse();
        expect($this->resolver->bloqueaAvanceDeFase($entrega, $pivotB->refresh()))->toBeTrue();

        // A is genuinely approved and must not be blocked.
        expect($this->resolver->estaAprobada($entrega, $pivotA->refresh()))->toBeTrue();
        expect($this->resolver->bloqueaAvanceDeFase($entrega, $pivotA->refresh()))->toBeFalse();
    });

    /**
     * (7) Protection of the intentional legacy behavior: grades still fall back
     * to the template, because they are a historical value, not another
     * project's outcome.
     */
    it('la nota si hereda consolidated_grade de la plantilla cuando el pivote no tiene nota', function () {
        $entrega = entregaAisladaContexto($this, 'aprobada');
        $entrega->update(['consolidated_grade' => 3.5]);

        $pivotA = entregaAisladaPivot($entrega, $this->proyectoA);

        expect($pivotA->getRawOriginal('director_grade'))->toBeNull();
        expect($this->resolver->nota($entrega->refresh(), $pivotA))->toBe(3.5);
        expect(entregaAisladaEstadoEnEndpoint($this, $entrega))->toBe('pendiente');
    });
});