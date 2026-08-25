<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evaluacion;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReporteController extends Controller
{
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

        $entregas = $proyecto->entregasPivot->map(function ($entrega) {
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
                'status' => $entrega->status,
                'promedio_ponderado' => $promedio,
            ];
        });

        $promedioGeneral = null;
        $notas = $entregas->pluck('promedio_ponderado')->filter();

        if ($notas->isNotEmpty()) {
            $promedioGeneral = round($notas->avg(), 2);
        }

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
                'estado' => $proyecto->status,
            ],
        ]);
    }
}
