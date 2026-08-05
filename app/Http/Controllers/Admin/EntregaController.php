<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Actions\Entrega\HabilitarEntregaAction;
use App\Actions\Entrega\ReviewEntregaAction;
use App\Actions\Entrega\SolicitarEntregaAction;
use App\Actions\Entrega\StoreEntregaAction;
use App\Actions\Entrega\UpdateEntregaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntregaRequest;
use App\Http\Requests\UpdateEntregaRequest;
use App\Models\Entrega;
use App\Models\VersionDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{
    public function __construct(
        private readonly StoreEntregaAction $storeEntregaAction,
        private readonly UpdateEntregaAction $updateEntregaAction,
        private readonly ReviewEntregaAction $reviewEntregaAction,
        private readonly SolicitarEntregaAction $solicitarEntregaAction,
        private readonly HabilitarEntregaAction $habilitarEntregaAction,
    ) {}

    /**
     * GET /api/admin/entregas
     *
     * Listar entregas con filtros opcionales por proyecto_id y fase.
     * Coordinador ve todas, director las suyas, estudiante las suyas.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Entrega::query()->with([
            'semestre:id,name',
            'proyecto:id,code,title,semester_id',
            'proyecto.semestre:id,name',
            'proyectos:id,code,title',
        ]);

        // Role-based scoping — check via pivot table AND direct proyecto_id
        if ($user->role->value === 'Director') {
            $query->where(function ($q) use ($user) {
                $q->whereHas('proyecto', fn ($sq) => $sq->where('director_id', $user->id))
                    ->orWhereHas('proyectos', fn ($sq) => $sq->where('director_id', $user->id));
            });
        } elseif ($user->role->value === 'Estudiante') {
            $query->where(function ($q) use ($user) {
                $q->whereHas('proyecto.estudiantes', fn ($sq) => $sq->where('user_id', $user->id))
                    ->orWhereHas('proyectos.estudiantes', fn ($sq) => $sq->where('user_id', $user->id));
            });
        }

        // Filter by grupo_id (semester): direct filter on semester_id
        if ($request->filled('grupo_id')) {
            $query->where('semester_id', $request->integer('grupo_id'));
        }

        if ($request->filled('proyecto_id')) {
            $query->where(function ($q) use ($request) {
                $pid = $request->integer('proyecto_id');
                $q->where('proyecto_id', $pid)
                    ->orWhereHas('proyectos', fn ($sq) => $sq->where('proyecto_id', $pid));
            });
        }

        if ($request->filled('fase')) {
            $query->where('phase', $request->input('fase'));
        }

        $entregas = $query->orderByDesc('created_at')->get();

        // Attach semestre_nombre and project info to each entrega
        $data = $entregas->map(function (Entrega $e) {
            $arr = $e->toArray();
            $arr['semestre_nombre'] = $e->semestre?->name ?? $e->proyecto?->semestre?->name ?? '—';
            $arr['proyectos_count'] = $e->proyectos->count();
            $arr['proyectos_list'] = $e->proyectos->map(fn ($p) => "{$p->code} - {$p->title}");

            return $arr;
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * PUT /api/admin/entregas/{id}
     *
     * Actualizar todos los campos editables de una entrega (coordinador).
     */
    public function update(UpdateEntregaRequest $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        try {
            $entrega = $this->updateEntregaAction->handle($entrega, $request->validated());
        } catch (EntregaActionException $e) {
            return $this->actionError($e);
        }

        $entrega->load('semestre:id,name', 'proyecto:id,code,title,semester_id', 'proyecto.semestre:id,name', 'proyectos:id,code,title');

        $arr = $entrega->toArray();
        // Canonical: the entrega's own semester_id (entrega_proyecto pivot
        // links projects, not proyecto_id). Fallback to project-derived
        // values only for legacy rows where semester_id is null.
        $arr['semestre_nombre'] = $entrega->semestre?->name ?? ($entrega->proyecto?->semestre?->name ?? '—');
        $arr['proyectos_count'] = $entrega->proyectos->count();
        $arr['proyectos_list'] = $entrega->proyectos->map(fn ($p) => "{$p->code} - {$p->title}");

        return response()->json(['data' => $arr]);
    }

    /**
     * DELETE /api/admin/entregas/{id}
     *
     * Eliminar una entrega (coordinador).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role->value !== 'Coordinador') {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $entrega = Entrega::findOrFail($id);
        $entrega->delete();

        return response()->json(['message' => 'Entrega eliminada correctamente.']);
    }

    /**
     * POST /api/admin/entregas
     *
     * Crear una nueva entrega (coordinador).
     */
    public function store(StoreEntregaRequest $request): JsonResponse
    {
        $entrega = $this->storeEntregaAction->handle($request->validated());

        return response()->json(['data' => $entrega], 201);
    }

    /**
     * POST /api/entregas/{id}/solicitar
     *
     * Estudiante solicita habilitación para subir versiones.
     */
    public function solicitar(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);
        $user = $request->user();

        try {
            $entrega = $this->solicitarEntregaAction->handle($entrega, $user->id, $request->ip(), $request->userAgent());
        } catch (EntregaActionException $e) {
            return $this->actionError($e);
        }

        return response()->json(['data' => $entrega]);
    }

    /**
     * GET /api/admin/entregas/{id}
     *
     * T-013: Show a single entrega with project info for director's review.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::with([
            'proyecto:id,code,title,director_id',
            'proyecto.estudiantes:id,name',
            'proyectos:id,code,title',
            'proyectos.estudiantes:id,name',
            'semestre:id,name',
            'versiones' => fn ($q) => $q->orderByDesc('version_number'),
            'versiones.entregaProyecto',
        ])->findOrFail($id);

        // Authorize: director of linked project, student of linked project, or coordinator
        $user = $request->user();
        $role = $user->role->value;
        $esDirector = $this->esDirectorDeEntrega($entrega, $user->id);
        $esEstudiante = $this->esEstudianteDeEntrega($entrega, $user->id);

        if (! in_array($role, ['Coordinador', 'Director', 'Estudiante'], true)) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if ($role === 'Director' && ! $esDirector) {
            return response()->json(['error' => 'No eres el director de este proyecto.'], 403);
        }

        if ($role === 'Estudiante' && ! $esEstudiante) {
            return response()->json(['error' => 'No eres estudiante de este proyecto.'], 403);
        }

        $data = $entrega->toArray();
        $data['proyectos_count'] = $entrega->proyectos->count();
        $data['versiones_count'] = $entrega->versiones->count();

        // D3-rev: each version exposes the director_grade of ITS per-project
        // delivery (EntregaProyecto). The review UI shows the note of the
        // selected version's project, never a shared template grade.
        $data['versiones'] = $entrega->versiones->map(function (VersionDocumento $version) {
            $pivot = $version->entregaProyecto;
            $array = $version->toArray();
            $array['director_grade'] = $pivot?->director_grade !== null
                ? (float) $pivot->director_grade
                : null;

            return $array;
        })->values()->toArray();

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /api/admin/entregas/{id}/habilitar
     *
     * Director habilita la entrega para que el estudiante suba versiones.
     */
    public function habilitar(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);
        $user = $request->user();

        if (! $this->esDirectorDeEntrega($entrega, $user->id)) {
            return response()->json(['error' => 'No eres el director de este proyecto.'], 403);
        }

        try {
            $entrega = $this->habilitarEntregaAction->handle($entrega, $user->id, $request->ip(), $request->userAgent());
        } catch (EntregaActionException $e) {
            return $this->actionError($e);
        }

        return response()->json(['data' => $entrega]);
    }

    /**
     * GET /api/entregas/{id}/versiones
     *
     * Historial de versiones de una entrega.
     */
    public function versiones(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        // Scope by role
        $user = $request->user();

        if ($user->role->value === 'Estudiante') {
            $esEstudiante = $this->esEstudianteDeEntrega($entrega, $user->id);

            if (! $esEstudiante) {
                return response()->json(['error' => 'No autorizado.'], 403);
            }
        }

        $versiones = VersionDocumento::where('entrega_id', $id)
            ->orderByDesc('version_number')
            ->get();

        return response()->json(['data' => $versiones]);
    }

    /**
     * DELETE /api/entregas/{entregaId}/versiones/{versionId}
     *
     * Eliminar una versión de documento (estudiante, solo si no tiene observaciones del director).
     */
    public function eliminarVersion(Request $request, int $entregaId, int $versionId): JsonResponse
    {
        $entrega = Entrega::findOrFail($entregaId);
        $user = $request->user();

        // Only the student of the linked project can delete
        $esEstudiante = $this->esEstudianteDeEntrega($entrega, $user->id);

        if (! $esEstudiante) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $version = VersionDocumento::where('entrega_id', $entregaId)
            ->where('id', $versionId)
            ->firstOrFail();

        // Can only delete if director hasn't made observations
        if ($version->director_notes && trim($version->director_notes) !== '') {
            return response()->json([
                'error' => 'No se puede eliminar una versión que ya tiene observaciones del director.',
            ], 422);
        }

        // Delete the stored file
        if ($version->file_path && Storage::disk('public')->exists($version->file_path)) {
            Storage::disk('public')->delete($version->file_path);
        }

        $version->delete();

        return response()->json(['message' => 'Versión eliminada correctamente.']);
    }

    /**
     * PUT /api/admin/entregas/{id}/revisar
     *
     * Director aprueba/rechaza entrega con nota y feedback.
     */
    public function revisar(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        // Verify the user is the director of any linked project
        $user = $request->user();

        if (! $this->esDirectorDeEntrega($entrega, $user->id)) {
            return response()->json(['error' => 'No eres el director de este proyecto.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:aprobada,rechazada,revisada',
            'consolidated_grade' => 'nullable|numeric|min:0|max:5',
            'director_notes' => 'nullable|string',
            'version_id' => 'required|integer|exists:versiones_documento,id',
            'director_grade' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // RF-NOT-01 / D7: director_grade range (0-5) and max 2 decimals,
        // validated only when the review approves (RF-NOT-02).
        if (($data['status'] ?? null) === 'aprobada' && isset($data['director_grade'])) {
            $grade = (float) $data['director_grade'];

            if ($grade < 0 || $grade > 5) {
                return $this->errorEnvelope(422, 'La nota del director debe estar entre 0 y 5');
            }

            if (round($grade, 2) !== $grade) {
                return $this->errorEnvelope(422, 'La nota del director debe tener máximo 2 decimales');
            }
        }

        try {
            $entrega = $this->reviewEntregaAction->handle($entrega, $data, $user->id);
        } catch (EntregaActionException $e) {
            return $this->errorEnvelope($e->status, $e->getMessage());
        }

        return response()->json(['data' => $entrega]);
    }

    /**
     * GET /api/admin/entregas/finales
     *
     * Banco de documentos aprobados (solo coordinador).
     */
    public function finales(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'Coordinador') {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $query = Entrega::where('status', 'aprobada')
            ->with([
                'proyecto:id,code,title,director_id',
                'proyectos:id,code,title,director_id',
                'versiones' => fn ($q) => $q->latest(),
            ]);

        if ($request->filled('proyecto_id')) {
            $query->where(function ($q) use ($request) {
                $pid = $request->integer('proyecto_id');
                $q->where('proyecto_id', $pid)
                    ->orWhereHas('proyectos', fn ($sq) => $sq->where('proyecto_id', $pid));
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->where('due_date', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('due_date', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('director_id')) {
            $query->whereHas('proyecto', fn ($q) => $q->where('director_id', $request->integer('director_id')));
        }

        $entregas = $query->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($entregas);
    }

    /**
     * Spec-compliant error envelope: {"error": {"message": "..."}} (RF-NOT-01/03).
     */
    private function errorEnvelope(int $status, string $message): JsonResponse
    {
        return response()->json(['error' => ['message' => $message]], $status);
    }

    /**
     * Render a domain rejection from an action back to the legacy
     * `{"error": "..."}` JSON shape with the original HTTP status.
     */
    private function actionError(EntregaActionException $e): JsonResponse
    {
        return response()->json(['error' => $e->getMessage()], $e->status);
    }

    /**
     * Check if a user is a student of any project linked to this entrega.
     */
    private function esEstudianteDeEntrega(Entrega $entrega, int $userId): bool
    {
        return $entrega->esEstudiante($userId);
    }

    /**
     * Check if a user is the director of any project linked to this entrega.
     */
    private function esDirectorDeEntrega(Entrega $entrega, int $userId): bool
    {
        return $entrega->esDirector($userId);
    }
}
