<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Evaluacion::query()
            ->with(['evaluador:id,name,email', 'entrega:id', 'entrega.proyectos:id,code,title']);

        if ($request->has('entrega_id')) {
            $validator = Validator::make($request->all(), [
                'entrega_id' => 'required|exists:entregas,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $query->where('entrega_id', $request->integer('entrega_id'));
        }

        if ($user->role->value !== 'Coordinador') {
            $query->where('evaluador_id', $user->id);
        }

        $evaluaciones = $query->get();

        // When filtering by a specific entrega, return individual records (legacy/API contract).
        if ($request->has('entrega_id')) {
            return response()->json(['data' => $evaluaciones]);
        }

        // When no filter (e.g. page load), the frontend expects aggregated results
        // per project for the ResultsTable component. An entrega is a semester
        // template linked to every active project via the pivot, so each
        // evaluation is bucketed under each of its linked projects.
        $grouped = collect();

        foreach ($evaluaciones as $evaluacion) {
            $proyectos = $evaluacion->entrega?->proyectos ?? collect();

            if ($proyectos->isEmpty()) {
                $grouped->push(['proyecto_id' => 0, 'evaluacion' => $evaluacion]);
                continue;
            }

            foreach ($proyectos as $proyecto) {
                $grouped->push(['proyecto_id' => $proyecto->id, 'evaluacion' => $evaluacion]);
            }
        }

        $grouped = $grouped->groupBy('proyecto_id');

        $results = $grouped->map(function ($items, int $proyectoId) {
            $evaluaciones = $items->pluck('evaluacion');
            $grades = $evaluaciones->whereNotNull('grade')->pluck('grade')->toArray();
            $promedio = count($grades) > 0
                ? round(array_sum($grades) / count($grades), 2)
                : null;

            $proyecto = $evaluaciones->first()?->entrega?->proyectos->firstWhere('id', $proyectoId);

            return [
                'id' => $proyectoId,
                'proyecto_id' => $proyectoId,
                'proyecto_nombre' => $proyecto?->title ?? '',
                'proyecto_codigo' => $proyecto?->code ?? '',
                'estudiantes' => [],
                'director' => '',
                'fase' => 'Anteproyecto',
                'evaluadores' => $evaluaciones->pluck('evaluador.name')->unique()->values()->toArray(),
                'nota_promedio' => $promedio,
                'puntuaciones' => $grades,
            ];
        })->values()->toArray();

        return response()->json(['data' => $results]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'entrega_id' => 'required|exists:entregas,id',
            'criterio' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'grade' => 'nullable|numeric|min:0|max:5',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $entrega = Entrega::findOrFail($data['entrega_id']);

        // An entrega is a semester template linked to its projects via the
        // pivot; the evaluator is assigned to any of those projects.
        $proyectoIds = $entrega->proyectos()->pluck('proyectos.id');

        $esAsignado = EvaluadorProyecto::whereIn('proyecto_id', $proyectoIds)
            ->where('evaluador_id', $user->id)
            ->exists();

        if (! $esAsignado) {
            return response()->json(['error' => 'No estás asignado a este proyecto.'], 403);
        }

        $existingSum = Evaluacion::where('entrega_id', $data['entrega_id'])
            ->where('evaluador_id', $user->id)
            ->sum('percentage');

        $newSum = $existingSum + (float) $data['percentage'];

        if ($newSum > 100) {
            return response()->json([
                'errors' => ['percentage' => ['La suma de porcentajes por entrega no puede exceder 100%.']],
            ], 422);
        }

        $grade = $data['grade'] ?? null;

        $evaluacion = Evaluacion::create([
            'entrega_id' => $data['entrega_id'],
            'evaluador_id' => $user->id,
            'criterio' => $data['criterio'],
            'percentage' => $data['percentage'],
            'grade' => $grade,
            'comment' => $data['comment'] ?? null,
            'evaluated_at' => $grade !== null ? now() : null,
        ]);

        $evaluacion->load('evaluador:id,name,email');

        return response()->json(['data' => $evaluacion], 201);
    }

    public function consolidado(Request $request, int $entregaId): JsonResponse
    {
        $entrega = Entrega::findOrFail($entregaId);

        $evaluaciones = Evaluacion::where('entrega_id', $entregaId)
            ->whereNotNull('grade')
            ->get();

        if ($evaluaciones->isEmpty()) {
            return response()->json([
                'data' => [
                    'entrega_id' => $entregaId,
                    'criterios' => [],
                    'promedio_ponderado' => null,
                ],
            ]);
        }

        $totalWeighted = 0;
        $totalPercentage = 0;

        $criterios = $evaluaciones->map(function (Evaluacion $e) use (&$totalWeighted, &$totalPercentage) {
            $weighted = ((float) $e->grade * (float) $e->percentage) / 100;
            $totalWeighted += $weighted;
            $totalPercentage += (float) $e->percentage;

            return [
                'criterio' => $e->criterio,
                'percentage' => (float) $e->percentage,
                'grade' => (float) $e->grade,
            ];
        });

        $promedio = $totalPercentage > 0
            ? round(($totalWeighted / $totalPercentage) * 100, 1)
            : null;

        return response()->json([
            'data' => [
                'entrega_id' => $entregaId,
                'criterios' => $criterios,
                'promedio_ponderado' => $promedio,
            ],
        ]);
    }
}
