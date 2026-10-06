<?php

declare(strict_types=1);

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
 * GET /api/director/proyectos/{id} must describe the delivery of the requested
 * PROJECT, not the semester-wide entrega template.
 *
 * This endpoint used to serialize the raw `Entrega` model, so `entregas[].status`
 * was whatever verdict had reached the shared row and `entregas[]` carried no
 * grade at all. Two consequences, both wrong for a director:
 *
 *   1. the verdict shown belonged to ANOTHER project of the same semester (the
 *      template is shared, and whoever reached it first wins);
 *   2. there was no per-project grade to show, so the director could not see a
 *      grade the student and the coordinator screens already displayed.
 *
 * The student endpoint (Api\EstudianteController::entregas) and the resolver
 * already answered per project, so this endpoint was the last surface still
 * reading the template. The tests below pin the per-project payload and, above
 * all, the isolation: no template verdict may reach a project that has none.
 *
 * Grades keep their deliberate template fallback (a legacy historical value);
 * states do not. See App\Services\Entregas\NotaEntregaResolver.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->director = User::factory()->director()->create();
    $this->otroDirector = User::factory()->director()->create();
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
 * The shared template, already linked to both projects.
 */
function detalleEntrega(object $test, string $statusTemplate): Entrega
{
    $entrega = Entrega::create([
        'semester_id' => $test->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto compartido',
        'description' => 'El estudiante debe presentar el planteamiento del problema.',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => $statusTemplate,
    ]);

    $entrega->proyectos()->attach([$test->proyectoA->id, $test->proyectoB->id]);

    return $entrega;
}

function detallePivot(Entrega $entrega, Proyecto $proyecto): EntregaProyecto
{
    return EntregaProyecto::where('entrega_id', $entrega->id)
        ->where('proyecto_id', $proyecto->id)
        ->firstOrFail();
}

function detalleVersion(Entrega $entrega, EntregaProyecto $pivot): VersionDocumento
{
    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'version_number' => 1,
        'file_path' => 'entregas/anteproyecto.pdf',
        'file_size' => 2048,
        'original_name' => 'anteproyecto.pdf',
        'uploaded_at' => now(),
    ]);
}

/**
 * The delivery row for one entrega as the endpoint reports it.
 */
function detalleEntregaEnEndpoint(object $test, Entrega $entrega, Proyecto $proyecto): array
{
    $response = $test->actingAs($test->director)
        ->getJson("/api/director/proyectos/{$proyecto->id}");

    $response->assertOk();

    return collect($response->json('data.entregas'))->firstWhere('id', $entrega->id);
}

describe('Detalle de proyecto del director — entregas por proyecto', function () {

    /**
     * THE REGRESSION. A template verdict earned by another project must not be
     * reported as this project's delivery.
     *
     * Project A uploaded and was approved; project B did neither. Asking for B
     * must answer "nothing submitted", exactly as the student screen answers it
     * for the same shared template.
     */
    it('no reporta el veredicto de plantilla de otro proyecto al detalle de un proyecto sin veredicto propio', function () {
        $entrega = detalleEntrega($this, 'aprobada');

        $pivotA = detallePivot($entrega, $this->proyectoA);
        $pivotA->update(['estado' => 'aprobada', 'director_grade' => 4.8]);
        detalleVersion($entrega, $pivotA);

        $pivotB = detallePivot($entrega, $this->proyectoB);
        expect($pivotB->estado)->toBeNull()
            ->and(VersionDocumento::where('entrega_proyecto_id', $pivotB->id)->exists())->toBeFalse();

        // B has no verdict of its own: the template's 'aprobada' is not B's.
        expect($entrega->refresh()->status)->toBe(EstadoEntrega::Aprobada);

        $filaB = detalleEntregaEnEndpoint($this, $entrega, $this->proyectoB);

        expect($filaB['status'])->toBe('pendiente')
            ->and($filaB['status'])->not->toBe('aprobada');

        // And the same delivery still reads approved for the project that earned it.
        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA)['status'])->toBe('aprobada');
    });

    /**
     * The same leak through the submission state: a template left 'enviada' by
     * another project must not make a never-submitted delivery look submitted.
     */
    it('no reporta enviada de plantilla cuando este proyecto no subio ninguna version', function () {
        $entrega = detalleEntrega($this, 'enviada');

        detalleVersion($entrega, detallePivot($entrega, $this->proyectoA));

        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoB)['status'])
            ->toBe('pendiente');
    });

    /**
     * Submission is per project: a version on THIS project's own pivot is the
     * only evidence that THIS project submitted.
     */
    it('reporta enviada cuando este proyecto tiene una version en su propio pivote', function () {
        $entrega = detalleEntrega($this, 'pendiente');

        detalleVersion($entrega, detallePivot($entrega, $this->proyectoA));

        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA)['status'])
            ->toBe('enviada');
    });

    /**
     * The grade the endpoint previously did not return at all: the director's
     * grade for THIS project, read from THIS project's pivot.
     */
    it('reporta la nota del pivote del proyecto consultado', function () {
        $entrega = detalleEntrega($this, 'pendiente');

        detallePivot($entrega, $this->proyectoA)->update([
            'estado' => 'aprobada',
            'director_grade' => 4.5,
        ]);

        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA)['grade'])->toBe(4.5);
    });

    /**
     * Two projects graded differently off one template: each director request
     * must return its own grade, never the sibling's.
     */
    it('distingue las notas de dos proyectos que comparten la misma plantilla', function () {
        $entrega = detalleEntrega($this, 'pendiente');

        detallePivot($entrega, $this->proyectoA)->update(['estado' => 'rechazada', 'director_grade' => 2.0]);
        detallePivot($entrega, $this->proyectoB)->update(['estado' => 'aprobada', 'director_grade' => 4.9]);

        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA))
            ->toMatchArray(['status' => 'rechazada', 'grade' => 2.0])
            ->and(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoB))
            ->toMatchArray(['status' => 'aprobada', 'grade' => 4.9]);
    });

    /**
     * A rejection is a verdict and must survive verbatim — it is not laundered
     * into 'enviada' by the presence of a version row.
     */
    it('conserva el veredicto de rechazo del pivote aunque exista una version subida', function () {
        $entrega = detalleEntrega($this, 'aprobada');

        $pivot = detallePivot($entrega, $this->proyectoA);
        $pivot->update(['estado' => 'rechazada']);
        detalleVersion($entrega, $pivot);

        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA)['status'])
            ->toBe('rechazada');
    });

    /**
     * Grades keep the template fallback on purpose: `entregas.consolidated_grade`
     * predates the pivot, and dropping it would make real recorded grades vanish
     * from the director's view. Asserted so this per-project rework can never be
     * mistaken for permission to remove that fallback.
     */
    it('mantiene la nota legacy de consolidated_grade cuando el pivote no tiene nota', function () {
        $entrega = detalleEntrega($this, 'aprobada');
        $entrega->update(['consolidated_grade' => 3.5]);

        $fila = detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA);

        expect($fila['grade'])->toBe(3.5)
            ->and($fila['status'])->toBe('pendiente');
    });

    /**
     * An ungraded, never-submitted delivery reports a null grade — the frontend
     * renders '—' for it. It must not be reported as 0, which would read as a
     * failed submission instead of a missing one.
     */
    it('reporta grade null cuando la entrega no ha sido calificada', function () {
        $entrega = detalleEntrega($this, 'pendiente');

        expect(detalleEntregaEnEndpoint($this, $entrega, $this->proyectoA)['grade'])
            ->toBeNull();
    });

    /**
     * Envelope and project fields are consumed by other pages
     * (BitacorasProyecto reads id/code/title, RevisionBitacora reads code and
     * estudiantes), so they must survive the entrega rework untouched.
     */
    it('conserva el envelope data y los campos de proyecto que consumen otras paginas', function () {
        detalleEntrega($this, 'pendiente');

        $response = $this->actingAs($this->director)
            ->getJson("/api/director/proyectos/{$this->proyectoA->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->proyectoA->id)
            ->assertJsonPath('data.code', $this->proyectoA->code)
            ->assertJsonPath('data.title', 'Proyecto A')
            ->assertJsonPath('data.semestre.id', $this->semestre->id)
            ->assertJsonPath('data.estudiantes.0.name', $this->estudiante->name);

        // Fields the ProjectDelivery interface declares non-optional.
        $entrega = collect($response->json('data.entregas'))->first();

        expect($entrega)->toHaveKeys(['id', 'title', 'due_date', 'phase', 'status', 'grade'])
            ->and($entrega['phase'])->toBe('anteproyecto')
            ->and($entrega['due_date'])->toBe(now()->addMonth()->toDateString());
    });

    /**
     * The endpoint stays scoped to the requesting director's projects.
     */
    it('no expone el detalle de un proyecto de otro director', function () {
        detalleEntrega($this, 'pendiente');

        $this->actingAs($this->otroDirector)
            ->getJson("/api/director/proyectos/{$this->proyectoA->id}")
            ->assertNotFound();
    });
});
