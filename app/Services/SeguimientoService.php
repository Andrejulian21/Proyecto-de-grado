<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\SeguimientoObservacion;
use App\Models\VersionDocumento;
use Illuminate\Support\Facades\DB;

class SeguimientoService
{
    /**
     * Returns 'entregada', 'no_entrego', or 'pendiente'.
     */
    public function calcularEstadoEntrega(Entrega $entrega, int $proyectoId): string
    {
        $tieneVersion = VersionDocumento::whereHas('entregaProyecto', function ($q) use ($entrega, $proyectoId) {
            $q->where('entrega_id', $entrega->id)
              ->where('proyecto_id', $proyectoId);
        })->exists();

        // Fallback: versiones con FK directa
        if (! $tieneVersion) {
            $tieneVersion = $entrega->versiones()->exists();
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
     * @return array{semestre: array, proyectos: array}
     */
    public function obtenerSeguimiento(int $semestreId): array
    {
        $semestre = \App\Models\Semestre::findOrFail($semestreId);

        $proyectos = Proyecto::with([
            'entregas',
            'bitacoras',
            'entregasPivot',
            'estudiantes',
            'director',
        ])->where('semester_id', $semestreId)
            ->orderBy('code')
            ->get();

        $observaciones = SeguimientoObservacion::where('semestre_id', $semestreId)
            ->get()
            ->keyBy(fn ($o) => $o->proyecto_id . '-' . $o->fase);

        $proyectosData = [];

        foreach ($proyectos as $proyecto) {
            // Merge direct + pivot entregas, deduplicate
            $merged = $proyecto->entregas
                ->concat($proyecto->entregasPivot)
                ->unique('id')
                ->values();

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
                $key = $proyecto->id . '-' . $faseKey;
                if (isset($observaciones[$key])) {
                    $obsArray[] = [
                        'fase' => $faseKey,
                        'contenido' => $observaciones[$key]->observacion ?? '',
                    ];
                }
            }

            $bitacoras = $this->contarBitacorasPorGrupo($proyecto->id);

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
