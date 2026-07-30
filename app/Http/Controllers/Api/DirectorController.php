<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectorController extends Controller
{
    /**
     * GET /api/director/proyectos
     *
     * Returns all projects assigned to the authenticated director
     * in active semesters, with students and semester info.
     */
    public function proyectos(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Proyecto::where('director_id', $userId)
            ->with(['estudiantes:id,name', 'semestre:id,name,is_active']);

        if (! $request->boolean('todas')) {
            $query->enSemestresActivos();
        }

        $proyectos = $query->get();

        return response()->json(['data' => $proyectos]);
    }

    /**
     * GET /api/director/kpis
     *
     * Returns KPI counts for the director's dashboard.
     */
    public function kpis(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $proyectoIds = Proyecto::where('director_id', $userId)
            ->enSemestresActivos()
            ->pluck('id');

        $proyectosSupervisando = $proyectoIds->count();

        $entregasPendientes = Entrega::where(function ($q) use ($proyectoIds) {
                foreach ($proyectoIds as $pid) {
                    $q->orWhere(function ($sq) use ($pid) {
                        $sq->paraProyecto($pid);
                    });
                }
            })
            ->where('status', 'enviada')
            ->count();

        $alertas = Proyecto::whereIn('id', $proyectoIds)
            ->where('status', 'en_riesgo')
            ->count();

        $aprobadasMes = Entrega::where(function ($q) use ($proyectoIds) {
                foreach ($proyectoIds as $pid) {
                    $q->orWhere(function ($sq) use ($pid) {
                        $sq->paraProyecto($pid);
                    });
                }
            })
            ->where('status', 'aprobada')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'data' => [
                'proyectos_supervisando' => $proyectosSupervisando,
                'entregas_pendientes'    => $entregasPendientes,
                'alertas'                => $alertas,
                'aprobadas_mes'          => $aprobadasMes,
            ],
        ]);
    }

    /**
     * GET /api/director/entregas
     *
     * Returns the 20 most urgent pending deliveries (status = 'enviada')
     * from the director's projects, ordered by due_date ASC.
     */
    public function entregas(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $proyectoIds = Proyecto::where('director_id', $userId)
            ->enSemestresActivos()
            ->pluck('id');

        $entregas = Entrega::where(function ($q) use ($proyectoIds) {
                foreach ($proyectoIds as $pid) {
                    $q->orWhere(function ($sq) use ($pid) {
                        $sq->paraProyecto($pid);
                    });
                }
            })
            ->where('status', 'enviada')
            ->with([
                'proyecto:id,code,title,director_id',
                'proyectos:id,code,title',
            ])
            ->orderBy('due_date', 'asc')
            ->take(20)
            ->get();

        // Load students for each project, handling both direct and pivot relations
        $entregas->load('proyecto.estudiantes:id,name');
        $entregas->load('proyectos.estudiantes:id,name');

        $result = $entregas->map(function ($entrega) {
            $primerProyecto = $entrega->proyecto ?? $entrega->proyectos->first();
            $primerEstudiante = $primerProyecto?->estudiantes->first();

            return [
                'id'          => $entrega->id,
                'proyecto_id' => $primerProyecto?->id,
                'codigo'      => $primerProyecto?->code,
                'proyecto'    => $primerProyecto?->title,
                'title'       => $entrega->title,
                'estudiante'  => $primerEstudiante?->name ?? '—',
                'due_date'    => $entrega->due_date?->toDateString(),
                'status'      => $entrega->status?->value ?? $entrega->status,
            ];
        });

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/director/proyectos/{id}/bitacoras
     *
     * T-010: Returns all bitácoras for a project owned by the director.
     */
    public function bitacoras(Request $request, int $id): JsonResponse
    {
        $proyecto = Proyecto::where('director_id', $request->user()->id)
            ->findOrFail($id);

        $bitacoras = Bitacora::where('proyecto_id', $proyecto->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($b) => [
                'id'               => $b->id,
                'topic'            => $b->topic,
                'notes'            => $b->notes,
                'semana'           => $b->semana,
                'meeting_date'     => $b->meeting_date?->toDateString(),
                'duration_hours'   => $b->duration_hours,
                'signature_status' => $b->signature_status?->value,
                'created_at'       => $b->created_at->toISO8601String(),
            ]);

        return response()->json(['data' => $bitacoras]);
    }

    /**
     * GET /api/director/proyectos/{id}
     *
     * T-011: Single project detail for the director's supervision view.
     */
    public function proyectoDetalle(Request $request, int $id): JsonResponse
    {
        $proyecto = Proyecto::where('director_id', $request->user()->id)
            ->findOrFail($id);

        $proyecto->load([
            'semestre',
            'estudiantes:id,name',
        ]);

        // Load ALL entregas: direct FK + pivot-linked
        $entregas = Entrega::paraProyecto($proyecto->id)
            ->orderByDesc('due_date')
            ->get();

        $proyecto->setRelation('entregas', $entregas);

        return response()->json(['data' => $proyecto]);
    }

    /**
     * GET /api/director/evaluaciones
     *
     * T-018: Projects where the director is assigned as evaluator
     * (via evaluador_proyecto pivot), excluding projects they direct.
     * Includes: students, co-evaluators, semester, and assigned phase.
     * Only projects in active semesters.
     */
    public function evaluaciones(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $asignaciones = EvaluadorProyecto::where('evaluador_id', $userId)
            ->whereHas('proyecto', function ($q) use ($userId) {
                $q->where('director_id', '!=', $userId)
                  ->enSemestresActivos();
            })
            ->with([
                'proyecto:id,code,title,director_id,semester_id,current_phase,status',
                'proyecto.semestre:id,name,is_active',
                'proyecto.estudiantes:id,name',
            ])
            ->get()
            ->map(function (EvaluadorProyecto $ep) use ($userId) {
                $proyecto = $ep->proyecto;

                // Gather co-evaluators for this project (excluding self)
                $coEvaluadores = EvaluadorProyecto::where('proyecto_id', $proyecto->id)
                    ->where('evaluador_id', '!=', $userId)
                    ->with('evaluador:id,name,email')
                    ->get()
                    ->pluck('evaluador')
                    ->filter()
                    ->values();

                return [
                    'id'              => $proyecto->id,
                    'code'            => $proyecto->code,
                    'title'           => $proyecto->title,
                    'current_phase'   => $proyecto->current_phase?->value,
                    'status'          => $proyecto->status?->value,
                    'fase_asignada'   => $ep->fase,
                    'fecha'           => $ep->fecha?->toDateString(),
                    'hora_inicio'     => $ep->hora_inicio,
                    'hora_fin'        => $ep->hora_fin,
                    'estudiantes'     => $proyecto->estudiantes->map(fn ($e) => [
                        'id'   => $e->id,
                        'name' => $e->name,
                    ]),
                    'co_evaluadores'  => $coEvaluadores->map(fn ($e) => [
                        'id'    => $e->id,
                        'name'  => $e->name,
                        'email' => $e->email,
                    ]),
                    'semestre'        => $proyecto->semestre ? [
                        'id'        => $proyecto->semestre->id,
                        'name'      => $proyecto->semestre->name,
                        'is_active' => $proyecto->semestre->is_active,
                    ] : null,
                ];
            });

        return response()->json(['data' => $asignaciones]);
    }

    /**
     * GET /api/director/proyectos/{id}/entrega-fase
     *
     * T-019: Find the approved delivery for the specified phase.
     * Phase mapping: Anteproyecto → presentacion_anteproyecto,
     *                Final → presentacion_final
     */
    public function entregaFase(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $request->validate(['fase' => 'required|string']);

        $fase = $request->input('fase');

        // Map phase aliases to actual Entrega phase values
        $phaseMap = [
            'Anteproyecto'            => 'anteproyecto',
            'presentacion_anteproyecto' => 'presentacion_anteproyecto',
            'Desarrollo'              => 'desarrollo',
            'presentacion_final'      => 'presentacion_final',
            'Final'                   => 'presentacion_final',
        ];

        $phase = $phaseMap[$fase] ?? $fase;

        // Verify the user is assigned as evaluator for this project
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

}
