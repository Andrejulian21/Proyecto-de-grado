<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstudianteController extends Controller
{
    /**
     * GET /api/estudiante/proyecto
     *
     * Returns the active project for the authenticated student,
     * including director, co-students, semester, and deliveries
     * with their versions.
     */
    public function proyecto(Request $request): JsonResponse
    {
        $user = $request->user();

        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->with([
                'director:id,name,email',
                'estudiantes:id,name,email',
                'semestre:id,name,is_active',
                'entregas' => function ($q) {
                    $q->orderBy('due_date')->with('versiones');
                },
            ])
            ->first();

        if (! $proyecto) {
            return response()->json([
                'error' => 'No tienes un proyecto asignado.',
            ], 404);
        }

        return response()->json(['data' => $proyecto]);
    }

    /**
     * GET /api/estudiante/entregas
     *
     * Returns all deliveries for the student's project
     * with version count and last version info.
     */
    public function entregas(Request $request): JsonResponse
    {
        $user = $request->user();

        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->first();

        if (! $proyecto) {
            return response()->json([
                'error' => 'No tienes un proyecto asignado.',
            ], 404);
        }

        $entregas = $proyecto->entregas()
            ->with('versiones')
            ->orderBy('due_date')
            ->get()
            ->map(function ($entrega) {
                return [
                    'id'               => $entrega->id,
                    'fase'             => $entrega->phase?->value ?? $entrega->phase,
                    'titulo'           => $entrega->title,
                    'descripcion'      => $entrega->description,
                    'fecha_limite'     => $entrega->due_date?->toDateString(),
                    'estado'           => $entrega->status?->value ?? $entrega->status,
                    'nota'             => $entrega->consolidated_grade,
                    'criterios'        => $entrega->acceptance_criteria,
                    'total_versiones'  => $entrega->versiones->count(),
                    'ultima_version'   => $entrega->versiones->last()?->numero_version,
                ];
            });

        return response()->json(['data' => $entregas]);
    }
}
