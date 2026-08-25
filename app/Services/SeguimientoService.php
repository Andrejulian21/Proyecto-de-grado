<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\SeguimientoObservacion;
use App\Models\Semestre;
use App\Models\VersionDocumento;

class SeguimientoService
{
    /**
     * Returns 'entregada', 'no_entrego', or 'pendiente'.
     *
     * Uses the eager-loaded `versiones` (+ `entregaProyecto`) when present
     * (issue #53 — the service loop preloads them), otherwise falls back
     * to a query. Behaviour is identical to the query-based version:
     * a version whose pivot matches (entrega, proyecto) counts first, and
     * a legacy version without `entrega_proyecto_id` counts as fallback.
     */
    public function calcularEstadoEntrega(Entrega $entrega, int $proyectoId): string
    {
        $versiones = $entrega->relationLoaded('versiones')
            ? $entrega->versiones
            : $entrega->versiones()->get();

        $tieneVersion = $versiones->contains(function (VersionDocumento $version) use ($entrega, $proyectoId): bool {
            $pivot = $version->relationLoaded('entregaProyecto')
                ? $version->entregaProyecto
                : $version->entregaProyecto()->first();

            return $pivot !== null
                && (int) $pivot->entrega_id === $entrega->id
                && (int) $pivot->proyecto_id === $proyectoId;
        });

        // Fallback: versiones con FK directa (legacy, sin entrega_proyecto_id).
        if (! $tieneVersion) {
            $tieneVersion = $versiones->isNotEmpty();
        }

        if ($tieneVersion) {
            return 'entregada';
        }

        if ($entrega->due_date !== null && $entrega->due_date->isPast()) {
            return 'no_entrego';
        }

        return 'pendiente';
    }

    /**
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

        return ['total' => $total, 'grupo_a' => $grupoA, 'grupo_b' => $grupoB];
    }

    /**
     * @return array{total: int, grupo_a: int, grupo_b: int}
     */
    private function contarBitacorasEnMemoria(\Illuminate\Support\Collection $bitacoras): array
    {
        return [
            'total' => $bitacoras->count(),
            'grupo_a' => $bitacoras->filter(fn (Bitacora $b) => $b->semana >= 1 && $b->semana <= 16)->count(),
            'grupo_b' => $bitacoras->filter(fn (Bitacora $b) => $b->semana >= 17 && $b->semana <= 32)->count(),
        ];
    }

    /**
     * @return array{semestre: array, proyectos: array}
     */
    public function obtenerSeguimiento(int $semestreId): array
    {
        $semestre = Semestre::findOrFail($semestreId);

        // Issue #53: `versiones.entregaProyecto` se precarga para que
        // `calcularEstadoEntrega` no ejecute una consulta por entrega.
        $proyectos = Proyecto::with([
            'entregasPivot.versiones.entregaProyecto',
            'bitacoras',
            'estudiantes',
            'director',
        ])->where('semester_id', $semestreId)
            ->orderBy('code')
            ->get();

        $observaciones = SeguimientoObservacion::where('semestre_id', $semestreId)
            ->get()
            ->keyBy(fn ($o) => $o->proyecto_id.'-'.$o->fase);

        $proyectosData = [];

        foreach ($proyectos as $proyecto) {
            // The pivot is the single source of truth for entregas.
            $merged = $proyecto->entregasPivot;

            // Group by phase
            $fases = [];
            $faseOrden = ['anteproyecto', 'presentacion_anteproyecto', 'desarrollo', 'presentacion_final'];
            $faseLabels = [
                'anteproyecto' => 'Anteproyecto',
                'presentacion_anteproyecto' => 'Presentación Anteproyecto',
                'desarrollo' => 'Desarrollo',
                'presentacion_final' => 'Presentación Final',
            ];

            foreach ($faseOrden as $faseKey) {
                $entregasFase = $merged->filter(fn ($e) => $e->phase === $faseKey)->values();

                if ($entregasFase->isEmpty()) {
                    continue;
                }

                $fases[] = [
                    'fase' => $faseLabels[$faseKey] ?? $faseKey,
                    'key' => $faseKey,
                    'entregas' => $entregasFase->map(fn ($e) => [
                        'id' => $e->id,
                        'title' => $e->title,
                        'due_date' => $e->due_date?->format('Y-m-d'),
                        'estado' => $this->calcularEstadoEntrega($e, $proyecto->id),
                    ])->values()->toArray(),
                ];
            }

            // Observations per phase
            $obsArray = [];

            foreach ($faseOrden as $faseKey) {
                $key = $proyecto->id.'-'.$faseKey;

                if (isset($observaciones[$key])) {
                    $obsArray[] = [
                        'fase' => $faseKey,
                        'contenido' => $observaciones[$key]->observacion ?? '',
                    ];
                }
            }

            // Issue #53: la relación ya está precargada — contar en memoria
            // en vez de ejecutar tres COUNT(*) por proyecto.
            $bitacoras = $this->contarBitacorasEnMemoria($proyecto->bitacoras);

            $proyectosData[] = [
                'id' => $proyecto->id,
                'estudiantes' => $proyecto->estudiantes->pluck('name')->implode(', '),
                'proyecto_nombre' => $proyecto->title,
                'proyecto_codigo' => $proyecto->code ?? '',
                'director' => $proyecto->director?->name ?? 'Sin asignar',
                'fases' => $fases,
                'bitacoras_grupo_a' => $bitacoras['grupo_a'],
                'bitacoras_grupo_b' => $bitacoras['grupo_b'],
                'observaciones' => $obsArray,
            ];
        }

        return [
            'semestre' => [
                'id' => $semestre->id,
                'nombre' => $semestre->name,
            ],
            'proyectos' => $proyectosData,
        ];
    }
}
