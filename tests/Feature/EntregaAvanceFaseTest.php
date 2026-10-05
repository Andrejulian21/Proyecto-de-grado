<?php

declare(strict_types=1);

use App\Enums\FaseProyecto;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase auto-advance gate of ReviewEntregaAction.
 *
 * The gate used to ask a single question — "is this entrega approved?" — which
 * made a delivery whose deadline expired with nothing uploaded block the phase
 * FOREVER, while ConsultaNotasService was already scoring it 0 and closing the
 * final grade. Two gates with opposite semantics for the same state.
 *
 * A missed delivery must not freeze the project: once the deadline is gone the
 * student has nothing left to do, so the delivery stops blocking the phase.
 */
uses(RefreshDatabase::class);

/**
 * Two projects sharing the SAME entregas and the SAME director: the advance
 * must be resolved per project, never per entrega.
 */
function avanceFaseContexto(): array
{
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $semestre = Semestre::factory()->create(['is_active' => true]);

    $proyectoA = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    $proyectoB = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);

    $proyectoA->estudiantes()->attach($estudiante);
    $proyectoB->estudiantes()->attach($estudiante);

    return compact('director', 'estudiante', 'semestre', 'proyectoA', 'proyectoB');
}

function avanceFaseEntrega(Semestre $semestre, Proyecto ...$proyectos): Entrega
{
    $entrega = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => FaseProyecto::Anteproyecto->value,
        'title' => 'Entrega de anteproyecto',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => 'pendiente',
    ]);

    $entrega->proyectos()->attach(array_map(fn (Proyecto $p) => $p->id, $proyectos));

    return $entrega;
}

function avanceFasePivot(Entrega $entrega, Proyecto $proyecto): EntregaProyecto
{
    return EntregaProyecto::where('entrega_id', $entrega->id)
        ->where('proyecto_id', $proyecto->id)
        ->firstOrFail();
}

function avanceFaseVersion(Entrega $entrega, EntregaProyecto $pivot, int $numero = 1): VersionDocumento
{
    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'version_number' => $numero,
        'file_path' => 'entregas/avance.pdf',
        'file_size' => 1024,
        'original_name' => 'avance.pdf',
        'uploaded_at' => now(),
    ]);
}

function avanceFaseAprobar(User $director, Entrega $entrega, VersionDocumento $version, ?Proyecto $proyecto = null)
{
    return test()->actingAs($director)
        ->putJson("/api/admin/entregas/{$entrega->id}/revisar", array_filter([
            'proyecto' => $proyecto?->id,
            'status' => 'aprobada',
            'consolidated_grade' => 4.5,
            'director_grade' => 4.5,
            'director_notes' => 'Aprobado',
            'version_id' => $version->id,
        ], fn ($valor) => $valor !== null));
}

describe('Avance de fase — una entrega no entregada no bloquea', function () {

    it('una entrega vencida sin subir no bloquea el avance de fase', function () {
        $ctx = avanceFaseContexto();

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $revisada->update(['status' => 'enviada']);
        $version = avanceFaseVersion($revisada, avanceFasePivot($revisada, $ctx['proyectoA']));

        // Deadline already gone, nothing ever uploaded on this project's pivot.
        // Relative dates so the test cannot be invalidated by a fixed past date.
        $vencida = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $vencida->update(['due_date' => now()->subDay()->toDateString()]);

        avanceFaseAprobar($ctx['director'], $revisada, $version, $ctx['proyectoA'])->assertOk();

        expect($ctx['proyectoA']->refresh()->current_phase)->toBe(FaseProyecto::PresentacionAnteproyecto);

        // The advance is resolved per project: B never got its own verdict.
        expect($ctx['proyectoB']->refresh()->current_phase)->toBe(FaseProyecto::Anteproyecto);
    });

    it('una entrega con plazo abierto sin subir si bloquea el avance', function () {
        $ctx = avanceFaseContexto();

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $revisada->update(['status' => 'enviada']);
        $version = avanceFaseVersion($revisada, avanceFasePivot($revisada, $ctx['proyectoA']));

        // Deadline still open: the student can still upload, so it blocks.
        $abierta = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $abierta->update(['due_date' => now()->addDays(3)->toDateString()]);

        avanceFaseAprobar($ctx['director'], $revisada, $version, $ctx['proyectoA'])->assertOk();

        expect($ctx['proyectoA']->refresh()->current_phase)->toBe(FaseProyecto::Anteproyecto);
    });

    it('una entrega subida sin calificar bloquea el avance', function () {
        $ctx = avanceFaseContexto();

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $revisada->update(['status' => 'enviada']);
        $version = avanceFaseVersion($revisada, avanceFasePivot($revisada, $ctx['proyectoA']));

        // Uploaded but never graded by the director: work exists on the pivot,
        // so the phase waits for the verdict even though nothing else is due.
        $subida = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $subida->update(['status' => 'enviada']);
        avanceFaseVersion($subida, avanceFasePivot($subida, $ctx['proyectoA']));

        avanceFaseAprobar($ctx['director'], $revisada, $version, $ctx['proyectoA'])->assertOk();

        expect($ctx['proyectoA']->refresh()->current_phase)->toBe(FaseProyecto::Anteproyecto);
    });

    it('una entrega rechazada bloquea el avance', function () {
        $ctx = avanceFaseContexto();

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $revisada->update(['status' => 'enviada']);
        $version = avanceFaseVersion($revisada, avanceFasePivot($revisada, $ctx['proyectoA']));

        // The deadline ALSO expired and nothing was uploaded: the explicit
        // rejection still outranks the "nothing to do anymore" shortcut, because
        // `rechazada` reopens the pivot so the student can resubmit corrections.
        $rechazada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $rechazada->update(['due_date' => now()->subDay()->toDateString()]);
        avanceFasePivot($rechazada, $ctx['proyectoA'])->update(['estado' => 'rechazada']);

        avanceFaseAprobar($ctx['director'], $revisada, $version, $ctx['proyectoA'])->assertOk();

        expect($ctx['proyectoA']->refresh()->current_phase)->toBe(FaseProyecto::Anteproyecto);
    });

    it('el avance ocurre cuando todas las entregas de la fase estan aprobadas', function () {
        $ctx = avanceFaseContexto();

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $revisada->update(['status' => 'enviada']);
        $version = avanceFaseVersion($revisada, avanceFasePivot($revisada, $ctx['proyectoA']));

        $aprobada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        avanceFasePivot($aprobada, $ctx['proyectoA'])->update(['estado' => 'aprobada']);

        avanceFaseAprobar($ctx['director'], $revisada, $version, $ctx['proyectoA'])->assertOk();

        expect($ctx['proyectoA']->refresh()->current_phase)->toBe(FaseProyecto::PresentacionAnteproyecto);
    });

    it('una fase sin entregas vinculadas no hace avanzar el proyecto', function () {
        $ctx = avanceFaseContexto();

        // The director also directs an unrelated project, so the policy lets
        // him review an entrega whose own linked projects are a different one.
        $proyectoAjeno = Proyecto::factory()->create([
            'semester_id' => $ctx['semestre']->id,
            'director_id' => $ctx['director']->id,
        ]);

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA']);
        $revisada->update(['status' => 'enviada']);

        // A delivery of ANOTHER phase, linked to the project that the reviewed
        // version resolves to: that project has no anteproyecto entrega at all.
        $otraFase = avanceFaseEntrega($ctx['semestre'], $proyectoAjeno);
        $otraFase->update(['phase' => FaseProyecto::Desarrollo->value]);

        $version = VersionDocumento::create([
            'entrega_id' => $revisada->id,
            'entrega_proyecto_id' => avanceFasePivot($otraFase, $proyectoAjeno)->id,
            'version_number' => 1,
            'file_path' => 'entregas/ajena.pdf',
            'file_size' => 1024,
            'original_name' => 'ajena.pdf',
            'uploaded_at' => now(),
        ]);

        // Legacy global review (no `proyecto`): the reviewed project comes from
        // the version's own pivot, which is NOT the reviewed entrega's project.
        avanceFaseAprobar($ctx['director'], $revisada, $version)->assertOk();

        expect($proyectoAjeno->refresh()->current_phase)->toBe(FaseProyecto::Anteproyecto);
    });

    it('el avance no ocurre en la ultima fase', function () {
        $ctx = avanceFaseContexto();
        $ctx['proyectoA']->update(['current_phase' => FaseProyecto::PresentacionFinal->value]);

        $revisada = avanceFaseEntrega($ctx['semestre'], $ctx['proyectoA'], $ctx['proyectoB']);
        $revisada->update([
            'phase' => FaseProyecto::PresentacionFinal->value,
            'status' => 'enviada',
        ]);
        $version = avanceFaseVersion($revisada, avanceFasePivot($revisada, $ctx['proyectoA']));

        avanceFaseAprobar($ctx['director'], $revisada, $version, $ctx['proyectoA'])->assertOk();

        expect($ctx['proyectoA']->refresh()->current_phase)->toBe(FaseProyecto::PresentacionFinal);
    });
});
