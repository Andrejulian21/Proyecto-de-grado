<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\EvaluadorProyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluadorController extends Controller
{
    /**
     * GET /api/evaluador/evaluaciones
     *
     * Projects assigned to the authenticated evaluator in active semesters.
     */
    public function evaluaciones(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $asignaciones = EvaluadorProyecto::where('evaluador_id', $userId)
            ->whereHas('proyecto', fn ($q) => $q->enSemestresActivos())
            ->with([
                'proyecto:id,code,title,director_id,semester_id,current_phase,status',
                'proyecto.director:id,name',
                'proyecto.semestre:id,name,is_active',
                'proyecto.estudiantes:id,name',
            ])
            ->get()
            ->map(fn (EvaluadorProyecto $ep) => $this->mapAsignacion($ep, $userId));

        return response()->json(['data' => $asignaciones]);
    }

    /**
     * GET /api/evaluador/kpis
     */
    public function kpis(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $asignaciones = EvaluadorProyecto::where('evaluador_id', $userId)
            ->whereHas('proyecto', fn ($q) => $q->enSemestresActivos())
            ->with(['proyecto:id,code,title,director_id,semester_id,current_phase,status'])
            ->get();

        $completadas = 0;

        foreach ($asignaciones as $ep) {
            $status = $this->resolveEvaluationStatus($ep, $userId);
            if ($status['evaluation_status'] === 'evaluated') {
                $completadas++;
            }
        }

        $asignados = $asignaciones->count();

        return response()->json([
            'data' => [
                'proyectos_asignados' => $asignados,
                'evaluaciones_pendientes' => $asignados - $completadas,
                'evaluaciones_completadas' => $completadas,
            ],
        ]);
    }

    /**
     * GET /api/evaluador/proyectos/{id}/entrega-fase
     *
     * Same assignment-gated logic as DirectorController@entregaFase.
     */
    public function entregaFase(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $request->validate(['fase' => 'required|string']);

        $fase = $request->input('fase');
        $phase = $this->mapPhase($fase);

        $esAsignado = EvaluadorProyecto::where('proyecto_id', $id)
            ->where('evaluador_id', $userId)
            ->exists();

        if (! $esAsignado) {
            return response()->json(['error' => 'No estás asignado como evaluador de este proyecto.'], 403);
        }

        $entrega = Entrega::where(function ($q) use ($id) {
            $q->where('proyecto_id', $id)
                ->orWhereHas('proyectos', fn ($sq) => $sq->where('proyectos.id', $id));
        })
            ->where('phase', $phase)
            ->where('status', 'aprobada')
            ->with([
                'versiones' => fn ($q) => $q->orderByDesc('version_number'),
                'proyecto:id,code,title',
            ])
            ->first();

        if (! $entrega) {
            return response()->json([
                'error' => 'No se encontró una entrega aprobada para esta fase.',
            ], 404);
        }

        return response()->json(['data' => $entrega]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAsignacion(EvaluadorProyecto $ep, int $userId): array
    {
        $proyecto = $ep->proyecto;
        $eval = $this->resolveEvaluationStatus($ep, $userId);

        return [
            'id' => $proyecto->id,
            'code' => $proyecto->code,
            'title' => $proyecto->title,
            'current_phase' => $proyecto->current_phase?->value,
            'status' => $proyecto->status?->value,
            'fase_asignada' => $ep->fase,
            'fecha' => $ep->fecha?->toDateString(),
            'assigned_at' => $ep->assigned_at?->toIso8601String(),
            'hora_inicio' => $ep->hora_inicio,
            'hora_fin' => $ep->hora_fin,
            'director' => $proyecto->director ? [
                'id' => $proyecto->director->id,
                'name' => $proyecto->director->name,
            ] : null,
            'estudiantes' => $proyecto->estudiantes->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ])->values(),
            'semestre' => $proyecto->semestre ? [
                'id' => $proyecto->semestre->id,
                'name' => $proyecto->semestre->name,
                'is_active' => $proyecto->semestre->is_active,
            ] : null,
            'evaluation_status' => $eval['evaluation_status'],
            'rating' => $eval['rating'],
        ];
    }

    /**
     * @return array{evaluation_status: string, rating: float|null}
     */
    private function resolveEvaluationStatus(EvaluadorProyecto $ep, int $userId): array
    {
        $fase = $ep->fase ?: $ep->proyecto?->current_phase?->value;
        if ($fase === null) {
            return ['evaluation_status' => 'pending', 'rating' => null];
        }

        $phase = $this->mapPhase($fase);
        $proyectoId = $ep->proyecto_id;

        $entrega = Entrega::where(function ($q) use ($proyectoId) {
            $q->where('proyecto_id', $proyectoId)
                ->orWhereHas('proyectos', fn ($sq) => $sq->where('proyectos.id', $proyectoId));
        })
            ->where('phase', $phase)
            ->where('status', 'aprobada')
            ->first();

        if (! $entrega) {
            return ['evaluation_status' => 'pending', 'rating' => null];
        }

        $grades = Evaluacion::where('entrega_id', $entrega->id)
            ->where('evaluador_id', $userId)
            ->whereNotNull('grade')
            ->pluck('grade');

        if ($grades->isEmpty()) {
            return ['evaluation_status' => 'pending', 'rating' => null];
        }

        return [
            'evaluation_status' => 'evaluated',
            'rating' => round((float) $grades->avg(), 2),
        ];
    }

    private function mapPhase(string $fase): string
    {
        $phaseMap = [
            'Anteproyecto' => 'anteproyecto',
            'presentacion_anteproyecto' => 'presentacion_anteproyecto',
            'Desarrollo' => 'desarrollo',
            'presentacion_final' => 'presentacion_final',
            'Final' => 'presentacion_final',
        ];

        return $phaseMap[$fase] ?? $fase;
    }
}
