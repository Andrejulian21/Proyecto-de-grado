<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoProyecto;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProyectoRequest;
use App\Models\Evaluacion;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $grupoId = $request->input('grupo_id') ?? $request->input('semester_id');

        if ($grupoId !== null && $grupoId !== '') {
            $query->where('semester_id', (int) $grupoId);
        }

        // Search by keyword (code or title)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        // Issue #53: paginar en el servidor (antes devolvía todos los
        // proyectos sin límite; el frontend filtraba en memoria). Los
        // consumidores del frontend (useProyectos, ProjectAutocomplete)
        // ya navegan las páginas con per_page/page.
        $perPage = min(max((int) $request->query('per_page', 15), 1), 200);

        $proyectos = $query->paginate($perPage);

        return response()->json($proyectos);
    }

    public function show(Proyecto $proyecto): JsonResponse
    {
        $proyecto->load([
            'semestre',
            'director:id,name',
            'estudiantes:id,name',
            'entregasPivot',
        ]);

        // The pivot is the single source of truth for entregas.
        $proyecto->setRelation('entregas', $proyecto->entregasPivot);

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

        // Issue #51 — Defect 3: create inside a transaction so the
        // PostgreSQL advisory lock taken in Proyecto::creating() (keyed by
        // semester) is held until the code + student links are committed.
        // Without a wrapping transaction the lock would be released before
        // the insert and the sequential-code generation would race.
        $proyecto = DB::transaction(function () use ($data, $studentIds) {
            $proyecto = Proyecto::create([
                'title' => $data['title'],
                'semester_id' => $data['semester_id'],
                'director_id' => $data['director_id'] ?? null,
                'requires_group_justification' => $data['requires_group_justification'] ?? false,
            ]);

            if (! empty($studentIds)) {
                $proyecto->estudiantes()->attach($studentIds);
            }

            return $proyecto;
        });

        $proyecto->load(['semestre', 'director', 'estudiantes']);

        return response()->json(['data' => $proyecto], 201);
    }

    public function update(UpdateProyectoRequest $request, Proyecto $proyecto): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('title', $data)) {
            $proyecto->title = $data['title'];
        }

        if (array_key_exists('director_id', $data)) {
            $proyecto->director_id = $data['director_id'];
        }

        if (array_key_exists('status', $data)) {
            $proyecto->status = $data['status'];
        }

        $proyecto->save();

        if (array_key_exists('student_ids', $data)) {
            $proyecto->estudiantes()->sync($data['student_ids']);
        }

        $proyecto->load(['semestre', 'director:id,name,email', 'estudiantes:id,name,email']);

        return response()->json(['data' => $proyecto]);
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

    public function destroy(Request $request, Proyecto $proyecto): JsonResponse
    {
        // 1. Verificar si el proyecto tiene entregas con versiones subidas por el estudiante.
        //    Con el nuevo sistema, las entregas son compartidas entre proyectos del mismo
        //    semestre via pivot. Solo verificamos si ESTE proyecto tiene versiones asociadas.
        $tieneVersiones = \DB::table('entrega_proyecto')
            ->where('proyecto_id', $proyecto->id)
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('versiones_documento')
                    ->whereColumn('versiones_documento.entrega_proyecto_id', 'entrega_proyecto.id');
            })
            ->exists();

        if ($tieneVersiones) {
            return response()->json([
                'error' => 'No se puede eliminar el proyecto porque ya tiene entregas con versiones subidas.',
            ], 422);
        }

        // 2. Eliminar solo el proyecto y sus relaciones directas
        //    Las entregas NO se eliminan porque son compartidas entre proyectos del semestre.
        $proyecto->bitacoras()->forceDelete();
        $proyecto->estudiantes()->detach();
        \DB::table('entrega_proyecto')->where('proyecto_id', $proyecto->id)->delete();
        \DB::table('evaluador_proyecto')->where('proyecto_id', $proyecto->id)->delete();
        Evaluacion::where('proyecto_id', $proyecto->id)->forceDelete();
        $proyecto->forceDelete();

        // 3. AuditEvent
        AuditEvent::dispatch(
            $request->user(),
            'proyecto.deleted',
            "Proyecto {$proyecto->code} eliminado",
            ['proyecto_id' => $proyecto->id]
        );

        return response()->json(['message' => 'Proyecto eliminado correctamente.']);
    }
}
