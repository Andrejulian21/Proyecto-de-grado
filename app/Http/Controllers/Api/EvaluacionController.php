<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use App\Models\Entrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entrega_id' => 'required|exists:entregas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $query = Evaluacion::where('entrega_id', $request->integer('entrega_id'));

        if ($user->role->value !== 'Coordinador') {
            $query->where('evaluador_id', $user->id);
        }

        return response()->json([
            'data' => $query->with('evaluador:id,name,email')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'entrega_id' => 'required|exists:entregas,id',
            'criterio' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'grade' => 'nullable|numeric|min:0|max:100',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $entrega = Entrega::findOrFail($data['entrega_id']);

        $esAsignado = EvaluadorProyecto::where('proyecto_id', $entrega->proyecto_id)
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
            ? round(($totalWeighted / $totalPercentage) * 100, 2)
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
