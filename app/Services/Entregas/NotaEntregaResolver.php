<?php

declare(strict_types=1);

namespace App\Services\Entregas;

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\VersionDocumento;

/**
 * Resolves a delivery's grade and verdict within the context of ONE project.
 *
 * An `entrega` is a SEMESTER-WIDE TEMPLATE shared by every project:
 * `entregas.status`, `entregas.consolidated_grade` and
 * `entregas.evaluation_complete` describe the TEMPLATE, not the delivery of any
 * single project. Since fix `0c96202` the director's verdict is written to the
 * `entrega_proyecto` pivot (`estado`, `director_grade`,
 * `observaciones_director`), because writing it to the shared row would grade
 * every project at once.
 *
 * Every READ of grade, state or observations goes through here — but the two
 * readings answer DIFFERENT questions and therefore do NOT share one rule:
 *
 *   - A GRADE (`nota()`) falls back to the template on purpose. Grades were
 *     recorded in `entregas.consolidated_grade` before the pivot existed, and
 *     without the fallback they would vanish from student screens and reports.
 *     A legacy grade is a HISTORICAL VALUE about a real submission.
 *
 *   - A STATE (`estado()`) must NEVER fall back to the template. A template
 *     verdict describes ANOTHER PROJECT's outcome, not a missing historical
 *     value, so inheriting it reports a delivery the project never made. In
 *     production a student whose project never submitted saw delivery 2 as
 *     "Enviada" purely because a different project had submitted it, while the
 *     director supervision views (which scope correctly) showed the truth.
 *
 * The state rule is not cosmetic either: it feeds `estaAprobada()`, so a
 * template saying `aprobada` made `bloqueaAvanceDeFase()` report the phase of
 * EVERY project as unlocked.
 */
final class NotaEntregaResolver
{
    /**
     * The director's grade for the given project, or the legacy value when the
     * pivot still carries no grade.
     *
     * The template fallback here is INTENTIONAL and must not be "symmetric"
     * with `estado()`, which no longer reads the template. Grades recorded in
     * `entregas.consolidated_grade` predate the pivot, so dropping the fallback
     * would make existing grades disappear from student screens and reports —
     * a silent loss of real data. A legacy grade is a historical value about a
     * real submission; a legacy template state is another project's outcome.
     */
    public function nota(?Entrega $entrega, ?EntregaProyecto $pivot): ?float
    {
        $notaPivot = $this->aFloat($pivot?->getRawOriginal('director_grade'));

        if ($notaPivot !== null) {
            return $notaPivot;
        }

        return $this->aFloat($entrega?->getRawOriginal('consolidated_grade'));
    }

    /**
     * The delivery's state FOR THIS PROJECT, never for the template.
     *
     * Precedence, in this order:
     *
     *   1. the pivot's own verdict (`entrega_proyecto.estado`) when non-empty —
     *      authoritative, already per project, returned verbatim so a
     *      `rechazada` is not laundered into `enviada` by the presence of a
     *      version row;
     *   2. otherwise, if THIS project uploaded a version onto THIS project's
     *      pivot → `enviada`, because that version row is the only evidence
     *      that this project submitted this delivery;
     *   3. otherwise → `pendiente`.
     *
     * `entregas.status` is deliberately unread here. It is the verdict of
     * whichever project happened to reach the shared row first, so reading it
     * would both display another project's submission and — through
     * `estaAprobada()` — unlock this project's phase gate.
     *
     * Only the three states the student UI understands are produced:
     * `aprobada`, `enviada`, `pendiente`, plus whatever verdict the director
     * actually wrote. A `null` entrega yields `null`: without a delivery there
     * is nothing to state, and inventing `pendiente` would report a delivery
     * that does not exist.
     */
    public function estado(?Entrega $entrega, ?EntregaProyecto $pivot): ?string
    {
        if ($entrega === null) {
            return null;
        }

        $estadoPivot = $this->aTexto($pivot?->estado);

        if ($estadoPivot !== null) {
            return $estadoPivot;
        }

        return $pivot !== null && $this->tieneVersiones($pivot)
            ? 'enviada'
            : 'pendiente';
    }

    /**
     * Observaciones del director. Solo existen por proyecto
     * (`entrega_proyecto.observaciones_director`): no hay valor legacy.
     */
    public function observaciones(?EntregaProyecto $pivot): ?string
    {
        return $this->aTexto($pivot?->observaciones_director);
    }

    /**
     * ¿La entrega quedó calificada para ESE proyecto?
     *
     * Se deriva del veredicto del pivote (estado o nota) y, ante la ausencia
     * de veredicto, del flag legacy de la plantilla. Es un OR monótono a
     * propósito: un flag legacy en `true` nunca se pierde por no tener
     * veredicto propio, y un veredicto propio nunca se pierde porque el flag
     * legacy siga en `false`.
     */
    public function evaluacionCompleta(?Entrega $entrega, ?EntregaProyecto $pivot): bool
    {
        $tieneVeredicto = $pivot !== null
            && ($this->aTexto($pivot->estado) !== null || $this->aFloat($pivot->getRawOriginal('director_grade')) !== null);

        return $tieneVeredicto || (bool) ($entrega?->evaluation_complete ?? false);
    }

    /**
     * Is the delivery approved FOR THIS PROJECT? It is the predicate behind the
     * gates (phase advance, enablement).
     *
     * This delegates to `estado()` and adds NO template fallback of its own,
     * which is now correct rather than merely convenient: with the template
     * unread by `estado()`, the ONLY value that can compare equal to `aprobada`
     * is a verdict this project actually received. The predicate is therefore
     * project-scoped by construction — there is no longer a code path in which
     * the shared row answers it.
     *
     * A `null` entrega resolves to `null` and compares false, so a missing
     * delivery is never approved.
     */
    public function estaAprobada(?Entrega $entrega, ?EntregaProyecto $pivot): bool
    {
        return $this->estado($entrega, $pivot) === 'aprobada';
    }

    /**
     * Does this delivery still BLOCK the phase advance of its project?
     *
     * `estaAprobada()` is the wrong question for a gate. A delivery whose
     * deadline expired with nothing uploaded is not approved, but it is also not
     * actionable: the window is closed and the student has nothing left to do,
     * so it must stop blocking. Otherwise the final grade closes (it scores 0
     * through `EstadoEntregaNota::NoEntregada`) while the phase stays frozen
     * forever — two gates with opposite semantics for the same state.
     *
     * Precedence, mirroring `ConsultaNotasService::estadoDeEntrega`:
     *
     *   1. approved → does not block;
     *   2. an explicit director verdict that is not approval → blocks
     *      (`rechazada` reopens the pivot for corrections, so the phase waits);
     *   3. no pivot → blocks (no per-project evidence to judge, safe default);
     *   4. deadline elapsed AND nothing ever uploaded on that project's pivot →
     *      does not block;
     *   5. anything else (uploaded ungraded, deadline still open) → blocks.
     */
    public function bloqueaAvanceDeFase(?Entrega $entrega, ?EntregaProyecto $pivot): bool
    {
        if ($entrega === null) {
            return true;
        }

        if ($this->estaAprobada($entrega, $pivot)) {
            return false;
        }

        if ($pivot === null) {
            return true;
        }

        if ($this->aTexto($pivot->estado) !== null) {
            return true;
        }

        return ! ($this->plazoVencido($entrega) && ! $this->tieneVersiones($pivot));
    }

    /**
     * Has this project uploaded anything for this delivery?
     *
     * The boundary is the PIVOT, not the project and not the entrega template:
     * `VersionDocumento::scopeParaProyecto()` filters by project, so it would
     * count uploads made on OTHER entregas of the same project, and
     * `Entrega::versiones()` is the semester-wide relation across every linked
     * project. Only `entrega_proyecto_id` answers "did THIS project submit
     * THIS delivery". Versions whose pivot was cleared belong to no project and
     * are excluded for free.
     */
    private function tieneVersiones(EntregaProyecto $pivot): bool
    {
        return VersionDocumento::query()
            ->where('entrega_proyecto_id', $pivot->id)
            ->exists();
    }

    /**
     * Has the submission window closed?
     *
     * Without `hora_maxima` this replicates `ConsultaNotasService::plazoVencido`
     * exactly: `due_date` is cast to date (midnight), so the comparison is on
     * whole days and `isPast()` is never used — a delivery due TODAY is not a
     * miss yet, and handing out a zero before the window closed would grade
     * something nobody failed.
     *
     * With `hora_maxima` the window ends at `due_date + hora_maxima`, so the
     * exact timestamp is the only correct comparison: a whole-day test would
     * wrongly keep `hora_maxima = '00:00'` open for the whole due date. The
     * value is a free-form string column, so it is parsed by hand instead of
     * handed to a date parser that would throw mid-transaction.
     */
    private function plazoVencido(Entrega $entrega): bool
    {
        if ($entrega->due_date === null) {
            return false;
        }

        $horaMaxima = $this->aTexto($entrega->hora_maxima);

        if ($horaMaxima === null) {
            return $entrega->due_date->lt(now()->startOfDay());
        }

        $partes = explode(':', $horaMaxima);

        return $entrega->due_date->copy()
            ->setTime(
                (int) ($partes[0] ?? 0),
                (int) ($partes[1] ?? 0),
                (int) ($partes[2] ?? 0),
            )
            ->isPast();
    }

    /**
     * Pivote de (entrega, proyecto), o null si el proyecto no está vinculado.
     */
    public function pivote(Entrega $entrega, int $proyectoId): ?EntregaProyecto
    {
        return EntregaProyecto::query()
            ->where('entrega_id', $entrega->id)
            ->where('proyecto_id', $proyectoId)
            ->first();
    }

    private function aFloat(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }

    private function aTexto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
