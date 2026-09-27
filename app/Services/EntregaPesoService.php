<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entrega;
use Illuminate\Validation\ValidationException;

/**
 * Validates the weight rule per phase at semester level (RF-ENT-04).
 *
 * Each phase (anteproyecto, desarrollo) independently sums to 100%.
 * Presentación phases (presentacion_anteproyecto, presentacion_final)
 * do NOT participate in the grade_percentage system.
 *
 * Validation operates over ALL entregas of the semester (not per project)
 * and applies on both Store and Update. Entregas with grade_percentage =
 * NULL do not count.
 *
 * Create/update accept PARTIAL sums: they only block when
 * (existing NOT NULL phase sum) + (proposed value) exceeds 100%.
 * The exact-100% completeness check lives ONLY in validarCierrePar(),
 * which the future pair close/publish endpoint must call.
 */
final class EntregaPesoService
{
    private const PARES = [
        'anteproyecto' => ['anteproyecto'],
        'desarrollo' => ['desarrollo'],
        // Presentación phases do NOT participate in grade_percentage.
        'presentacion_anteproyecto' => [],
        'presentacion_final' => [],
    ];

    /**
     * Return the phases that participate in weight validation for the
     * given phase. Empty array means the phase does not participate.
     *
     * @return list<string>
     */
    public function fasesDelPar(string $fase): array
    {
        return self::PARES[$fase] ?? [];
    }

    /**
     * Sum of NOT NULL grade_percentage values for the pair in the semester.
     * When $excluirEntregaId is given (update flow), that entrega's own
     * current value is excluded so the new proposal is not double-counted.
     *
     * @param  list<string>  $fasesPar
     */
    public function obtenerSumaPar(int $semesterId, array $fasesPar, ?int $excluirEntregaId = null): float
    {
        return (float) Entrega::query()
            ->where('semester_id', $semesterId)
            ->whereIn('phase', $fasesPar)
            ->whereNotNull('grade_percentage')
            ->when($excluirEntregaId !== null, fn ($query) => $query->where('id', '!=', $excluirEntregaId))
            ->sum('grade_percentage');
    }

    /**
     * Enforce the pair weight rule for a proposed weight.
     *
     * A NULL proposal never blocks (RF-ENT-04: NULL does not participate).
     * Partial sums are allowed on create/update; only exceeding 100%
     * is rejected. The exact-100% requirement is enforced exclusively
     * at pair close/publish time via validarCierrePar().
     *
     * @throws ValidationException when the pair sum would exceed 100%.
     */
    public function validarSumaPar(int $semesterId, string $fase, ?float $nuevoPeso, ?int $excluirEntregaId = null): void
    {
        if ($nuevoPeso === null) {
            return;
        }

        $par = $this->fasesDelPar($fase);

        // Phase does not participate in the weight system (e.g. presentación).
        if ($par === []) {
            return;
        }

        $sumaActual = $this->obtenerSumaPar($semesterId, $par, $excluirEntregaId);
        $total = $sumaActual + $nuevoPeso;

        if ($total - 100.0 > 0.0001) {
            throw ValidationException::withMessages([
                'grade_percentage' => sprintf(
                    'La suma de porcentajes del par de fases superaría el 100%% (actual %s%% + nuevo %s%% = %s%%). La suma exacta del 100%% se valida al cierre del par.',
                    $this->formato($sumaActual),
                    $this->formato($nuevoPeso),
                    $this->formato($total),
                ),
            ]);
        }
    }

    /**
     * Enforce the exact-100% pair rule at pair close/publish time.
     *
     * TODO: no close/publish endpoint calls this yet. When the pair
     * close endpoint is built, it MUST call validarCierrePar() so a
     * phase cannot be closed while its weight sum differs from 100%.
     *
     * @param  list<string>  $fasesPar
     *
     * @throws ValidationException when the pair sum is not exactly 100%.
     */
    public function validarCierrePar(int $semesterId, array $fasesPar): void
    {
        if ($fasesPar === []) {
            return;
        }

        $sumaActual = $this->obtenerSumaPar($semesterId, $fasesPar);

        if (abs($sumaActual - 100.0) > 0.0001) {
            throw ValidationException::withMessages([
                'grade_percentage' => sprintf(
                    'La suma de porcentajes del par de fases debe ser exactamente 100%% (actual %s%%)',
                    $this->formato($sumaActual),
                ),
            ]);
        }
    }

    private function formato(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
