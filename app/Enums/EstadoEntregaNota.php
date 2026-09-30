<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Grading state of a delivery for ONE project.
 *
 * A NULL grade alone cannot answer the question the weighted average asks,
 * because it means two opposite things: "the student did not hand this in"
 * (which must score zero) and "nobody has ruled on it yet" (which must not
 * be scored at all). Dropping both cases from the average is what let a
 * missed delivery RAISE a student's grade.
 *
 * Every state is therefore derived from facts already on disk — no new
 * columns, no snapshot:
 *
 *   calificada              -> entrega_proyecto.director_grade is not null
 *   entregada_sin_calificar -> a version exists on THIS project's pivot
 *   no_entregada            -> deadline passed, delivery enabled, no version
 *   pendiente               -> deadline still open, no version
 *   no_iniciada             -> deadline passed, delivery never enabled
 */
enum EstadoEntregaNota: string
{
    case NoIniciada = 'no_iniciada';
    case Pendiente = 'pendiente';
    case NoEntregada = 'no_entregada';
    case EntregadaSinCalificar = 'entregada_sin_calificar';
    case Calificada = 'calificada';

    /**
     * Only a known outcome lets the phase compute. The other three are
     * "we cannot tell yet" states: they must block the phase instead of
     * silently shrinking the denominator.
     */
    public function computa(): bool
    {
        return $this === self::Calificada || $this === self::NoEntregada;
    }

    /**
     * The only state that scores zero instead of blocking.
     */
    public function penaliza(): bool
    {
        return $this === self::NoEntregada;
    }
}
