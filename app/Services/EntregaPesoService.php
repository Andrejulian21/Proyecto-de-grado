<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entrega;
use Illuminate\Validation\ValidationException;

/**
 * Validates the 100% weight rule per phase at semester level (RF-ENT-04).
 *
 * Each phase (anteproyecto, desarrollo) independently sums to 100%.
 * Presentación phases (presentacion_anteproyecto, presentacion_final)
 * do NOT participate in the grade_percentage system.
 *
 * Validation operates over ALL entregas of the semester (not per project)
 * and applies on both Store and Update. Entregas with grade_percentage =
 * NULL do not count.
 *
 * Preventive block: (existing NOT NULL phase sum) + (proposed value) > 100.
 * Completeness: when a phase already has at least one entrega with a NOT
 * NULL percentage, the phase sum MUST be exactly 100.
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
     * Enforce the 100% pair rule for a proposed weight.
     *
     * A NULL proposal never blocks (RF-ENT-04: NULL does not participate).
     *
     * @throws ValidationException when the pair sum would exceed 100% or,
     *                             when both phases already carry weights,
     *                             the pair sum is not exactly 100%.
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

        if ($this->parCompleto($semesterId, $par, $excluirEntregaId) && abs($total - 100.0) > 0.0001) {
            throw ValidationException::withMessages([
                'grade_percentage' => sprintf(
                    'La suma de porcentajes del par de fases debe ser exactamente 100%% (actual %s%% + nuevo %s%% = %s%%)',
                    $this->formato($sumaActual),
                    $this->formato($nuevoPeso),
                    $this->formato($total),
                ),
            ]);
        }

        if ($total > 100.0) {
            throw ValidationException::withMessages([
                'grade_percentage' => sprintf(
                    'La suma de porcentajes del par de fases superaría el 100%% (actual %s%% + nuevo %s%% = %s%%)',
                    $this->formato($sumaActual),
                    $this->formato($nuevoPeso),
                    $this->formato($total),
                ),
            ]);
        }
    }

    /**
     * Whether ALL deliveries in the phase already have a NOT NULL
     * grade_percentage. Only when every delivery has a weight does
     * the phase become "complete" and the 100% exact rule fires.
     *
     * @param  list<string>  $par
     */
    private function parCompleto(int $semesterId, array $par, ?int $excluirEntregaId): bool
    {
        foreach ($par as $fase) {
            // Total deliveries in this phase for the semester (excluding the one being edited)
            $total = Entrega::query()
                ->where('semester_id', $semesterId)
                ->where('phase', $fase)
                ->when($excluirEntregaId !== null, fn ($query) => $query->where('id', '!=', $excluirEntregaId))
                ->count();

            if ($total === 0) {
                return false;
            }

            // Deliveries WITH a percentage
            $conPeso = Entrega::query()
                ->where('semester_id', $semesterId)
                ->where('phase', $fase)
                ->whereNotNull('grade_percentage')
                ->when($excluirEntregaId !== null, fn ($query) => $query->where('id', '!=', $excluirEntregaId))
                ->count();

            // If any delivery is missing a percentage, the phase is incomplete
            if ($conPeso < $total) {
                return false;
            }
        }

        return true;
    }

    private function formato(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
