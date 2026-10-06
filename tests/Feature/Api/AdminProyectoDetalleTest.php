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
 * GET /api/admin/proyectos/{id} must describe the delivery of the requested
 * PROJECT, not the semester-wide entrega template.
 *
 * This is the coordinator's supervision view, the last surface still
 * serializing the raw `Entrega` model after the director and student
 * endpoints were scoped per project. `entregas[].status` and
 * `entregas[].consolidated_grade` describe the SEMESTER-WIDE template, which
 * every project of the semester shares, so the coordinator saw whichever
 * project reached the shared row first: another project's verdict and another
 * project's grade.
 *
 * The tests below pin the per-project payload and, above all, the isolation: no
 * template verdict may reach a project that has none of its own.
 *
 * Grades keep their deliberate template fallback (a legacy historical value);
 * states do not. See App\Services\Entregas\NotaEntregaResolver.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->coordinador = User::factory()->coordinador()->create();
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
 * The shared template, already linked to both projects.
 */
function adminDetalleEntrega(object $test, string $statusTemplate): Entrega
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

function adminDetallePivot(Entrega $entrega, Proyecto $proyecto): EntregaProyecto
{
    return EntregaProyecto::where('entrega_id', $entrega->id)
        ->where('proyecto_id', $proyecto->id)
        ->firstOrFail();
}

function adminDetalleVersion(Entrega $entrega, EntregaProyecto $pivot): VersionDocumento
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
function adminDetalleFila(object $test, Entrega $entrega, Proyecto $proyecto): array
{
    $response = $test->actingAs($test->coordinador)
        ->getJson("/api/admin/proyectos/{$proyecto->id}");

    $response->assertOk();

    return collect($response->json('data.entregas'))->firstWhere('id', $entrega->id);
}

describe('Detalle de proyecto del administrador — entregas por proyecto', function () {

    /**
     * THE REGRESSION. A template verdict earned by another project must not be
     * reported as this project's delivery.
     *
     * Project A uploaded and was approved; project B did neither. Asking for B
     * must answer "nothing submitted", exactly as the student and director
     * screens answer it for the same shared template.
     */
    it('no reporta el veredicto de plantilla de otro proyecto al detalle de un proyecto sin veredicto propio', function () {
        $entrega = adminDetalleEntrega($this, 'aprobada');

        $pivotA = adminDetallePivot($entrega, $this->proyectoA);
        $pivotA->update(['estado' => 'aprobada', 'director_grade' => 4.8]);
        adminDetalleVersion($entrega, $pivotA);

        $pivotB = adminDetallePivot($entrega, $this->proyectoB);
        expect($pivotB->estado)->toBeNull()
            ->and(VersionDocumento::where('entrega_proyecto_id', $pivotB->id)->exists())->toBeFalse();

        // The shared row really does carry the sibling's verdict.
        expect($entrega->refresh()->status)->toBe(EstadoEntrega::Aprobada);

        $filaB = adminDetalleFila($this, $entrega, $this->proyectoB);

        expect($filaB['status'])->toBe('pendiente')
            ->and($filaB['status'])->not->toBe('aprobada');

        // And the same delivery still reads approved and graded for the project
        // that earned it.
        expect(adminDetalleFila($this, $entrega, $this->proyectoA))
            ->toMatchArray(['status' => 'aprobada', 'grade' => 4.8]);
    });

    /**
     * The same leak through the submission state: a template left 'enviada' by
     * another project must not make a never-submitted delivery look submitted.
     */
    it('no reporta enviada de plantilla cuando este proyecto no subio ninguna version', function () {
        $entrega = adminDetalleEntrega($this, 'enviada');

        adminDetalleVersion($entrega, adminDetallePivot($entrega, $this->proyectoA));

        expect(adminDetalleFila($this, $entrega, $this->proyectoB)['status'])
            ->toBe('pendiente');
    });

    /**
     * Submission is per project: a version on THIS project's own pivot is the
     * only evidence that THIS project submitted.
     */
    it('reporta enviada cuando este proyecto tiene una version en su propio pivote', function () {
        $entrega = adminDetalleEntrega($this, 'pendiente');

        adminDetalleVersion($entrega, adminDetallePivot($entrega, $this->proyectoA));

        expect(adminDetalleFila($this, $entrega, $this->proyectoA)['status'])
            ->toBe('enviada');
    });

    /**
     * Two projects graded differently off one template: each coordinator
     * request must return its own grade, never the sibling's.
     */
    it('distingue las notas de dos proyectos que comparten la misma plantilla', function () {
        $entrega = adminDetalleEntrega($this, 'pendiente');

        adminDetallePivot($entrega, $this->proyectoA)->update(['estado' => 'rechazada', 'director_grade' => 2.0]);
        adminDetallePivot($entrega, $this->proyectoB)->update(['estado' => 'aprobada', 'director_grade' => 4.9]);

        expect(adminDetalleFila($this, $entrega, $this->proyectoA))
            ->toMatchArray(['status' => 'rechazada', 'grade' => 2.0])
            ->and(adminDetalleFila($this, $entrega, $this->proyectoB))
            ->toMatchArray(['status' => 'aprobada', 'grade' => 4.9]);
    });

    /**
     * Grades keep the template fallback on purpose: `entregas.consolidated_grade`
     * predates the pivot, and dropping it would make real recorded grades vanish
     * from the coordinator's view. Asserted so this per-project rework can never
     * be mistaken for permission to remove that fallback.
     */
    it('mantiene la nota legacy de consolidated_grade cuando el pivote no tiene nota', function () {
        $entrega = adminDetalleEntrega($this, 'aprobada');
        $entrega->update(['consolidated_grade' => 3.5]);

        $fila = adminDetalleFila($this, $entrega, $this->proyectoA);

        expect($fila['grade'])->toBe(3.5)
            ->and($fila['status'])->toBe('pendiente');
    });

    /**
     * An ungraded, never-submitted delivery reports a null grade — the frontend
     * renders '—' for it. It must not be reported as 0, which would read as a
     * failed submission instead of a missing one.
     */
    it('reporta grade null cuando la entrega no ha sido calificada', function () {
        $entrega = adminDetalleEntrega($this, 'pendiente');

        expect(adminDetalleFila($this, $entrega, $this->proyectoA)['grade'])
            ->toBeNull();
    });

    /**
     * This endpoint is shared by three screens. The envelope and every project
     * field they read must survive the entrega rework untouched:
     * SupervisionReadOnly (code, title, estudiantes[].name, semestre.*,
     * current_phase, entregas[]), VerBitacorasCoordinador (id, code, title) and
     * RevisionBitacoraCoordinador (code, estudiantes[].name, director.name).
     */
    it('conserva el envelope data y los campos de proyecto que consumen otras paginas', function () {
        adminDetalleEntrega($this, 'pendiente');

        $response = $this->actingAs($this->coordinador)
            ->getJson("/api/admin/proyectos/{$this->proyectoA->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->proyectoA->id)
            ->assertJsonPath('data.code', $this->proyectoA->code)
            ->assertJsonPath('data.title', 'Proyecto A')
            ->assertJsonPath('data.current_phase', $this->proyectoA->current_phase->value)
            ->assertJsonPath('data.semestre.id', $this->semestre->id)
            ->assertJsonPath('data.semestre.name', $this->semestre->name)
            ->assertJsonPath('data.estudiantes.0.name', $this->estudiante->name)
            ->assertJsonPath('data.director.name', $this->director->name);

        $entrega = collect($response->json('data.entregas'))->first();

        expect($entrega)->toHaveKeys(['id', 'title', 'description', 'due_date', 'phase', 'status', 'grade'])
            ->and($entrega['phase'])->toBe('anteproyecto')
            ->and($entrega['due_date'])->toBe(now()->addMonth()->toDateString());
    });
});
