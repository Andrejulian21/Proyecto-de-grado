<?php

declare(strict_types=1);

namespace App\Services\Entregas;

use App\Models\Entrega;
use App\Models\EntregaProyecto;

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
