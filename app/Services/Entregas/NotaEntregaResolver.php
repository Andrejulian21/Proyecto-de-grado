<?php

declare(strict_types=1);

namespace App\Services\Entregas;

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\VersionDocumento;

/**
 * Resuelve la calificación de una entrega en el contexto de UN proyecto.
 *
 * Una `entrega` es una PLANTILLA compartida por todos los proyectos del
 * semestre: `entregas.status`, `entregas.consolidated_grade` y
 * `entregas.evaluation_complete` describen a la plantilla, no a la entrega de
 * un proyecto. Desde el fix `0c96202` el veredicto del director se escribe en
 * el pivote `entrega_proyecto` (`estado`, `director_grade`,
 * `observaciones_director`), porque escribirlo en la fila compartida
 * calificaría a todos los proyectos de una vez.
 *
 * Toda LECTURA de nota, estado u observaciones pasa por acá. La regla es una
 * sola, en un solo lugar:
 *
 *   1. el valor del pivote de ESE proyecto, si existe;
 *   2. si no, el valor legacy de la fila plantilla.
 *
 * El fallback no es cosmético: en producción hay notas escritas en
 * `entregas.consolidated_grade` antes del fix, y sin él desaparecerían de la
 * pantalla del estudiante y de los reportes.
 */
final class NotaEntregaResolver
{
    /**
     * Nota del director para el proyecto dado, o el valor legacy si el pivote
     * todavía no tiene veredicto.
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
     * Estado de la entrega para el proyecto dado. Sin veredicto propio, el
     * pivote cae al estado de la plantilla (comportamiento previo al fix).
     */
    public function estado(?Entrega $entrega, ?EntregaProyecto $pivot): ?string
    {
        $estadoPivot = $this->aTexto($pivot?->estado);

        if ($estadoPivot !== null) {
            return $estadoPivot;
        }

        $status = $entrega?->status;

        if ($status === null) {
            return null;
        }

        return is_string($status) ? $status : $status->value;
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
     * ¿La entrega está aprobada para ESE proyecto? Es el predicado de los
     * gates (avance de fase, habilitación): con la fila plantilla el resultado
     * sería el mismo para todos los proyectos de la entrega.
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
