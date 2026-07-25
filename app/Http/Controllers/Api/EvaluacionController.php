<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use App\Models\Entrega;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Evaluacion::query()
            ->with(['evaluador:id,name,email', 'entrega:id,proyecto_id']);

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
        // per project for the ResultsTable component.
        $grouped = $evaluaciones->groupBy(fn ($e) => $e->entrega?->proyecto_id ?? 0);

        $results = $grouped->map(function (Collection $items, int $proyectoId) {
            $grades = $items->whereNotNull('grade')->pluck('grade')->toArray();
            $promedio = count($grades) > 0
                ? round(array_sum($grades) / count($grades), 2)
                : null;

            return [
                'id' => $proyectoId,
                'proyecto_id' => $proyectoId,
                'proyecto_nombre' => '',
                'proyecto_codigo' => '',
                'estudiantes' => [],
                'director' => '',
                'fase' => 'Anteproyecto',
                'evaluadores' => $items->pluck('evaluador.name')->unique()->values()->toArray(),
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

        $entrega = Entrega::with('proyectos:id')->findOrFail($data['entrega_id']);

        // Entregas may link via direct FK and/or entrega_proyecto pivot
        // (same resolution pattern as EvaluadorController@entregaFase).
        $proyectoIds = collect([$entrega->proyecto_id])
            ->merge($entrega->proyectos->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        if ($proyectoIds->isEmpty()) {
            return response()->json([
                'error' => 'La entrega no está vinculada a ningún proyecto.',
            ], 422);
        }

        $asignacionUsuario = EvaluadorProyecto::whereIn('proyecto_id', $proyectoIds)
            ->where('evaluador_id', $user->id)
            ->exists();

        if (! $asignacionUsuario) {
            $proyectoTieneEvaluadores = EvaluadorProyecto::whereIn('proyecto_id', $proyectoIds)->exists();

            if (! $proyectoTieneEvaluadores) {
                return response()->json([
                    'error' => 'El proyecto no tiene un evaluador asignado.',
                ], 403);
            }

            return response()->json([
                'error' => 'El usuario autenticado no corresponde al evaluador asignado.',
            ], 403);
        }

        $yaExisteCriterio = Evaluacion::where('entrega_id', $data['entrega_id'])
            ->where('evaluador_id', $user->id)
            ->where('criterio', $data['criterio'])
            ->exists();

        if ($yaExisteCriterio) {
            return response()->json([
                'error' => 'La evaluación para este criterio ya fue enviada.',
            ], 422);
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
