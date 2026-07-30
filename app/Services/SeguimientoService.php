<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\SeguimientoObservacion;
use App\Models\VersionDocumento;

class SeguimientoService
{
    /**
     * Check whether the given proyecto has submitted at least one version
     * for the specified entrega.
     *
     * A version exists when VersionDocumento is linked through the
     * entrega_proyecto pivot (direct FK on VersionDocumento.entrega_proyecto_id).
     */
    public function calcularEstadoEntrega(Entrega $entrega, int $proyectoId): bool
    {
        return VersionDocumento::whereHas('entregaProyecto', function ($q) use ($entrega, $proyectoId) {
            $q->where('entrega_id', $entrega->id)
              ->where('proyecto_id', $proyectoId);
        })->exists();
    }

    /**
     * Count the number of bitácoras per group for a given proyecto.
     *
     * - grupo_a: semanas 1–16
     * - grupo_b: semanas 17–32
     * Returns total, grupo_a, and grupo_b counts.
     *
     * @return array{total: int, grupo_a: int, grupo_b: int}
     */
    public function contarBitacorasPorGrupo(int $proyectoId): array
    {
        $total = Bitacora::where('proyecto_id', $proyectoId)->count();

        $grupoA = Bitacora::where('proyecto_id', $proyectoId)
            ->whereBetween('semana', [1, 16])
            ->count();

        $grupoB = Bitacora::where('proyecto_id', $proyectoId)
            ->whereBetween('semana', [17, 32])
            ->count();

        return [
            'total' => $total,
            'grupo_a' => $grupoA,
            'grupo_b' => $grupoB,
        ];
    }

    /**
     * Get full seguimiento data for all projects in a given semester.
     *
     * Returns an array of projects, each with:
     *  - basic project info (id, code, title, current_phase, status)
     *  - entregas grouped by phase, each with estado (submitted or not)
     *  - bitacora counts per group
     *  - coordinator observations per phase
     *
     * @return array<int, array>
     */
    public function obtenerSeguimiento(int $semestreId): array
    {
        $proyectos = Proyecto::with([
            'entregas',
            'bitacoras',
            'entregasPivot',
        ])->where('semester_id', $semestreId)
            ->orderBy('code')
            ->get();

        $observaciones = SeguimientoObservacion::where('semestre_id', $semestreId)
            ->get()
            ->keyBy(fn ($o) => $o->proyecto_id . '-' . $o->fase);

        $result = [];

        foreach ($proyectos as $proyecto) {
            // Merge direct + pivot entregas, deduplicate by id
            $merged = $proyecto->entregas
                ->concat($proyecto->entregasPivot)
                ->unique('id')
                ->values();

            // Group entregas by phase
            $entregasPorFase = [];
            foreach ($merged as $entrega) {
                $phase = $entrega->phase;
                $entregasPorFase[$phase][] = [
                    'id' => $entrega->id,
                    'title' => $entrega->title,
                    'status' => $entrega->status,
                    'due_date' => $entrega->due_date?->format('Y-m-d'),
                    'tiene_version' => $this->calcularEstadoEntrega($entrega, $proyecto->id),
                ];
            }

            // Bitacora counts
            $bitacoras = $this->contarBitacorasPorGrupo($proyecto->id);

            // Observations grouped by phase
            $observacionesPorFase = [];
            foreach ($proyecto->entregas->merge($proyecto->entregasPivot)->unique('id') as $entrega) {
                $phase = $entrega->phase;
                $key = $proyecto->id . '-' . $phase;
                if (isset($observaciones[$key]) && ! isset($observacionesPorFase[$phase])) {
                    $observacionesPorFase[$phase] = $observaciones[$key]->observacion;
                }
            }

            $result[] = [
                'id' => $proyecto->id,
                'code' => $proyecto->code,
                'title' => $proyecto->title,
                'current_phase' => $proyecto->current_phase?->value ?? $proyecto->current_phase,
                'status' => $proyecto->status?->value ?? $proyecto->status,
                'entregas' => $entregasPorFase,
                'bitacoras' => $bitacoras,
                'observaciones' => $observacionesPorFase,
            ];
        }

        return $result;
    }
}
