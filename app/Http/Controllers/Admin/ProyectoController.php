<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoProyecto;
use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProyectoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Proyecto::query()
            ->with(['semestre', 'director:id,name,email', 'estudiantes:id,name,email'])
            ->orderByDesc('created_at');

        // Filter by active semester
        if ($request->boolean('semestre_activo')) {
            $query->enSemestresActivos();
        }

        // Search by keyword (code or title)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $proyectos = $query->get();

        return response()->json(['data' => $proyectos]);
    }

    public function show(Proyecto $proyecto): JsonResponse
    {
        $proyecto->load([
            'semestre',
            'director:id,name',
            'estudiantes:id,name',
            'entregas',
            'entregasPivot',
        ]);

        // Merge direct FK entregas + pivot-linked entregas, deduplicate by id
        $directEntregas = $proyecto->entregas;
        $pivotEntregas = $proyecto->entregasPivot;
        $merged = $directEntregas->concat($pivotEntregas)->unique('id')->values();

        $proyecto->setRelation('entregas', $merged);

        return response()->json(['data' => $proyecto]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'semester_id' => 'required|exists:semestres,id',
            'director_id' => 'nullable|exists:users,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $studentIds = $data['student_ids'] ?? [];

        if (count($studentIds) > 3) {
            return response()->json([
                'errors' => ['student_ids' => ['Máximo 3 estudiantes por proyecto.']],
            ], 422);
        }

        // Un estudiante no puede estar en más de un proyecto
        $alreadyAssigned = \DB::table('proyecto_estudiante')
            ->whereIn('user_id', $studentIds)
            ->pluck('user_id')
            ->toArray();

        if (! empty($alreadyAssigned)) {
            $names = User::whereIn('id', $alreadyAssigned)->pluck('name')->join(', ');
            return response()->json([
                'errors' => ['student_ids' => ["Los siguientes estudiantes ya tienen un proyecto asignado: {$names}"]],
            ], 422);
        }

        if (count($studentIds) >= 3) {
            $data['requires_group_justification'] = true;
        }

        $proyecto = Proyecto::create([
            'title' => $data['title'],
            'semester_id' => $data['semester_id'],
            'director_id' => $data['director_id'] ?? null,
            'requires_group_justification' => $data['requires_group_justification'] ?? false,
        ]);

        if (! empty($studentIds)) {
            $proyecto->estudiantes()->attach($studentIds);
        }

        $proyecto->load(['semestre', 'director', 'estudiantes']);

        return response()->json(['data' => $proyecto], 201);
    }

    public function kpis(): JsonResponse
    {
        $activos = Proyecto::enSemestresActivos()
            ->where('status', '!=', EstadoProyecto::Completado->value)
            ->count();

        $enRiesgo = Proyecto::enSemestresActivos()
            ->where('status', EstadoProyecto::EnRiesgo->value)
            ->count();

        $alertas = Proyecto::enSemestresActivos()
            ->where('alert_count', '>', 0)
            ->count();

        $total = Proyecto::enSemestresActivos()->count();
        $completados = Proyecto::enSemestresActivos()
            ->where('status', EstadoProyecto::Completado->value)
            ->count();

        $tasa = $total > 0 ? round(($completados / $total) * 100, 1) : 100.0;

        return response()->json([
            'proyectos_activos' => $activos,
            'en_riesgo' => $enRiesgo,
            'alertas_sin_revisar' => $alertas,
            'tasa_cumplimiento' => $tasa,
        ]);
    }
}
