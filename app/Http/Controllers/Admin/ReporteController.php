<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Evaluacion;
use App\Models\Proyecto;
use App\Services\Entregas\NotaEntregaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReporteController extends Controller
{
    public function __construct(
        private readonly NotaEntregaResolver $notas,
    ) {}

    public function consolidado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $proyecto = Proyecto::with(['director', 'estudiantes', 'entregasPivot'])->findOrFail(
            $request->integer('proyecto_id')
        );

        // The director grade and the verdict live on the project's own
        // entrega_proyecto pivot; the semester template is only the legacy
        // fallback (see NotaEntregaResolver). Aggregating the template row
        // would either report nothing or report another project's grade.
        $pivotes = EntregaProyecto::query()
            ->where('proyecto_id', $proyecto->id)
            ->get()
            ->keyBy('entrega_id');

        $entregas = $proyecto->entregasPivot->map(function (Entrega $entrega) use ($pivotes) {
            $pivot = $pivotes->get($entrega->id);

            $evaluaciones = Evaluacion::where('entrega_id', $entrega->id)
                ->whereNotNull('grade')
                ->get();

            $promedio = null;
            $totalWeighted = 0;
            $totalPercentage = 0;

            foreach ($evaluaciones as $e) {
                $totalWeighted += (float) $e->grade * (float) $e->percentage;
                $totalPercentage += (float) $e->percentage;
            }

            if ($totalPercentage > 0) {
                $promedio = round($totalWeighted / $totalPercentage, 2);
            }

            return [
                'id' => $entrega->id,
                'title' => $entrega->title,
                'phase' => $entrega->phase,
                'status' => $this->notas->estado($entrega, $pivot),
                'nota' => $this->notas->nota($entrega, $pivot),
                'evaluacion_completa' => $this->notas->evaluacionCompleta($entrega, $pivot),
                'promedio_ponderado' => $promedio,
            ];
        });

        $promedioGeneral = null;
        $notas = $entregas->pluck('promedio_ponderado')->filter();

        if ($notas->isNotEmpty()) {
            $promedioGeneral = round($notas->avg(), 2);
        }

        // Average of the director grades of THIS project's deliveries.
        // `promedio_general` above is the weighted average of the external
        // evaluator criteria and keeps its own meaning; the two are not
        // interchangeable, so the director-grade aggregate is reported apart
        // instead of silently replacing it.
        $notasDirector = $entregas->pluck('nota')->filter(fn ($nota) => $nota !== null);

        $promedioNotas = $notasDirector->isNotEmpty()
            ? round($notasDirector->avg(), 2)
            : null;

        return response()->json([
            'data' => [
                'proyecto' => [
                    'id' => $proyecto->id,
                    'code' => $proyecto->code,
                    'title' => $proyecto->title,
                    'current_phase' => $proyecto->current_phase,
                ],
                'estudiantes' => $proyecto->estudiantes->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'email' => $e->email,
                ]),
                'director' => $proyecto->director ? [
                    'id' => $proyecto->director->id,
                    'name' => $proyecto->director->name,
                    'email' => $proyecto->director->email,
                ] : null,
                'entregas' => $entregas,
                'promedio_general' => $promedioGeneral,
                'promedio_notas' => $promedioNotas,
                'estado' => $proyecto->status,
            ],
        ]);
    }
}
