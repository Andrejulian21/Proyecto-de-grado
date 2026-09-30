<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Actions\Entrega\HabilitarEntregaAction;
use App\Actions\Entrega\ReviewEntregaAction;
use App\Actions\Entrega\SolicitarEntregaAction;
use App\Actions\Entrega\StoreEntregaAction;
use App\Actions\Entrega\UpdateEntregaAction;
use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntregaRequest;
use App\Http\Requests\UpdateEntregaRequest;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\Entregas\VersionIsolationScope;
use App\Services\Evaluation\AiFeedbackPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private readonly VersionIsolationScope $versionIsolation,
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
            'proyectos:id,code,title',
        ]);

        // Role-based scoping — moved to the model scope (issue #38, #47).
        // Coordinator unfiltered (intentional); Director/Estudiante see
        // their projects; EvaluadorExterno sees only assigned projects.
        $query->paraUsuario($user);

        // Filter by grupo_id (semester): direct filter on semester_id
        if ($request->filled('grupo_id')) {
            $query->where('semester_id', $request->integer('grupo_id'));
        }

        if ($request->filled('proyecto_id')) {
            $query->whereHas('proyectos', fn ($sq) => $sq->where('proyecto_id', $request->integer('proyecto_id')));
        }

        if ($request->filled('fase')) {
            $query->where('phase', $request->input('fase'));
        }

        $entregas = $query->orderByDesc('created_at')->get();

        // Attach semestre_nombre and project info to each entrega
        $data = $entregas->map(function (Entrega $e) {
            $arr = $e->toArray();
            $arr['semestre_nombre'] = $e->semestre?->name ?? '—';
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

        $entrega->load('semestre:id,name', 'proyectos:id,code,title');

        $arr = $entrega->toArray();
        // Canonical: the entrega's own semester_id (entrega_proyecto pivot
        // links projects, not proyecto_id).
        $arr['semestre_nombre'] = $entrega->semestre?->name ?? '—';
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
        $this->authorize('delete', Entrega::class);

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

        // Issue #38: only students of a linked project may request
        // habilitación (the action also enforces the membership rule).
        $this->authorize('solicitar', $entrega);

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
     *
     * An entrega is a SEMESTER-WIDE template, so this detail has two modes:
     *
     * - `?proyecto=<id>` — the entrega resolved INSIDE one project. Only that
     *   project's versions, grade, notes and AI analyses are loaded, and the
     *   per-project delivery is published as the `entrega_proyecto` block.
     *   This is what the director's review screen needs: without it the page
     *   cannot know which project it is grading and silently shows the
     *   documents and grades of every other project of the semester.
     * - No `proyecto` — GLOBAL supervision. Deliberately preserved: the
     *   coordinator, the review index, the semester reports and every other
     *   existing caller read the entrega as a whole, and narrowing them by
     *   default would remove data they legitimately need.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        // Issue #38: central rule — coordinator, director of a linked
        // project, student of a linked project, or assigned evaluator.
        // Authorization runs BEFORE the eager load so an unauthorized actor
        // never triggers the version/analysis queries.
        $this->authorize('view', $entrega);

        $actor = $request->user();
        $proyectoId = $this->proyectoSolicitado($request);
        $entregaProyecto = $this->resolverPivoteDeProyecto($entrega, $proyectoId, $actor);

        $entrega->load([
            'proyectos:id,code,title',
            'proyectos.estudiantes:id,name',
            'semestre:id,name',
            // A student only sees the versions uploaded by their own projects;
            // every other role supervises the whole entrega (see
            // VersionIsolationScope). When a project is requested, the pivot
            // narrows it further for EVERY role, including the supervisors.
            'versiones' => function ($q) use ($actor, $proyectoId) {
                $query = $this->versionIsolation->apply($q, $actor);

                if ($proyectoId !== null) {
                    $query->paraProyecto([$proyectoId]);
                }

                return $query->orderByDesc('version_number');
            },
            'versiones.entregaProyecto',
            'versiones.analisisIa',
        ]);

        $data = $entrega->toArray();
        $data['proyectos_count'] = $entrega->proyectos->count();
        $data['versiones_count'] = $entrega->versiones->count();

        // Issue #47: a public-disk path is downloadable without a session,
        // so it never leaves the API for a student (the supervisor roles keep
        // it to locate the file they must review).
        $exposeFilePath = $actor->role !== UserRole::Estudiante;

        // D3-rev: each version exposes the director_grade of ITS per-project
        // delivery (EntregaProyecto). The review UI shows the note of the
        // selected version's project, never a shared template grade.
        $data['versiones'] = $entrega->versiones->map(function (VersionDocumento $version) use ($exposeFilePath) {
            $pivot = $version->entregaProyecto;
            $array = $version->toArray();
            unset($array['analisis_ia']);

            if (! $exposeFilePath) {
                unset($array['file_path']);
            }

            $array['director_grade'] = $pivot?->director_grade !== null
                ? (float) $pivot->director_grade
                : null;
            $array['analisis_ia'] = $version->analisisIa
                ->map(fn (AiDocumentEvaluation $evaluation) => AiFeedbackPresenter::toArray($evaluation))
                ->values()
                ->all();

            return $array;
        })->values()->toArray();

        // Only present when the request named a project: without it there is
        // no single per-project delivery to describe, and inventing one would
        // reintroduce the ambiguity this filter exists to remove.
        if ($entregaProyecto !== null) {
            $data['entrega_proyecto'] = [
                'id' => $entregaProyecto->id,
                'proyecto_id' => $entregaProyecto->proyecto_id,
                'estado' => $entregaProyecto->estado,
                'director_grade' => $entregaProyecto->director_grade !== null
                    ? (float) $entregaProyecto->director_grade
                    : null,
                // `observaciones_director` is the pivot's own copy of the
                // director feedback; the version keeps its per-version note.
                'director_notes' => $entregaProyecto->observaciones_director,
                'versiones' => $data['versiones'],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Read the optional `proyecto` filter (an integer project id).
     */
    private function proyectoSolicitado(Request $request): ?int
    {
        if (! $request->filled('proyecto')) {
            return null;
        }

        $proyectoId = $request->integer('proyecto');

        return $proyectoId > 0 ? $proyectoId : null;
    }

    /**
     * Resolve the requested project's per-project delivery, enforcing that the
     * actor is related to THAT project. Returns null when no project was
     * requested (global supervision).
     *
     * The two rejections are deliberately ordered. An actor with no relation to
     * the project at all gets a 403 that says nothing about this entrega; only
     * once the relation holds does a missing pivot become a 404. Otherwise the
     * status code would answer "is project X linked to this entrega?" for
     * anybody who can guess an id.
     */
    private function resolverPivoteDeProyecto(Entrega $entrega, ?int $proyectoId, User $actor): ?EntregaProyecto
    {
        if ($proyectoId === null) {
            return null;
        }

        if (! $this->versionIsolation->relacionadoConProyecto($actor, $proyectoId)) {
            $this->jsonError(403, 'No autorizado.');
        }

        $entregaProyecto = $this->versionIsolation->resolverPivote($entrega, $proyectoId);

        if ($entregaProyecto === null) {
            $this->jsonError(404, 'No se encontró la entrega para el proyecto indicado.');
        }

        return $entregaProyecto;
    }

    /**
     * Abort the request with a JSON error envelope instead of Laravel's
     * English default page. `abort()` accepts a ready Response instance.
     */
    private function jsonError(int $status, string $message): never
    {
        abort(response()->json(['error' => $message], $status));
    }

    /**
     * PUT /api/admin/entregas/{id}/habilitar
     *
     * Director habilita la entrega para que el estudiante suba versiones.
     *
     * Pass `proyecto=<id>` to re-enable ONE project's delivery: only that
     * pivot's grade is cleared. Without it the unfreeze stays semester-wide
     * (legacy behaviour).
     */
    public function habilitar(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        // Issue #38: only the director of a linked project enables.
        $this->authorize('habilitar', $entrega);

        $user = $request->user();

        $entregaProyecto = $this->resolverPivoteDeProyecto(
            $entrega,
            $this->proyectoSolicitado($request),
            $user,
        );

        try {
            $entrega = $this->habilitarEntregaAction->handle(
                $entrega,
                $user->id,
                $request->ip(),
                $request->userAgent(),
                $entregaProyecto,
            );
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

        // Issue #38: central rule. Fixes the derived finding where the
        // version list was only checked for the Estudiante role.
        $this->authorize('view', $entrega);

        $versiones = $this->versionIsolation->apply(
            VersionDocumento::where('entrega_id', $id),
            $request->user(),
        )
            ->orderByDesc('version_number')
            ->get();

        // Issue #47 (hallazgo 4): never expose file_path (a public-disk
        // path downloadable without a session). Return the version id and
        // the rest of the metadata instead.
        $data = $versiones
            ->map(function (VersionDocumento $version) {
                $arr = $version->toArray();
                unset($arr['file_path']);

                return $arr;
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * DELETE /api/entregas/{entregaId}/versiones/{versionId}
     *
     * Eliminar una versión de documento (estudiante, solo si no tiene observaciones del director).
     */
    public function eliminarVersion(Request $request, int $entregaId, int $versionId): JsonResponse
    {
        $entrega = Entrega::findOrFail($entregaId);

        // Issue #38: only the student of a linked project may delete a
        // version. Director/coordinador/evaluador are denied here (403).
        $this->authorize('deleteVersion', $entrega);

        $user = $request->user();

        // Issue #46: the entrega is a shared template, but the version
        // belongs to ONE project delivery (entrega_proyecto_id). Resolve the
        // owning project through the pivot and verify the authenticated
        // student is a member of THAT project — not merely of any linked one.
        $version = VersionDocumento::with('entregaProyecto.proyecto')
            ->where('entrega_id', $entregaId)
            ->where('id', $versionId)
            ->firstOrFail();

        $proyecto = $version->entregaProyecto?->proyecto;

        if (! $proyecto || ! $proyecto->estudiantes()->where('user_id', $user->id)->exists()) {
            // 404, not 403: do not confirm that a document belonging to
            // another project exists (issue #46 acceptance criteria).
            return response()->json(['error' => 'No autorizado.'], 404);
        }

        // RF-FREEZE-01: a graded pivot freezes version deletes for that
        // project delivery only.
        $pivot = $version->entregaProyecto;

        if ($pivot !== null && $pivot->director_grade !== null) {
            return response()->json([
                'code' => 'PIVOT_FROZEN',
                'error' => 'La entrega ya fue calificada por el director. Solicita una habilitación para modificar las versiones.',
            ], 403);
        }

        // Can only delete if director hasn't made observations
        if ($version->director_notes && trim($version->director_notes) !== '') {
            return response()->json([
                'error' => 'No se puede eliminar una versión que ya tiene observaciones del director.',
            ], 422);
        }

        // Issue #46: leave an immutable trace before the irreversible
        // deletion (version id, owning project and user).
        AuditEvent::dispatch(
            $user,
            'version.deleted',
            "Versión #{$version->id} de la entrega #{$entregaId} eliminada por su autor.",
            [
                'version_id' => $version->id,
                'entrega_id' => $entregaId,
                'entrega_proyecto_id' => $version->entrega_proyecto_id,
                'proyecto_id' => $proyecto->id,
            ],
        );

        $filePath = $version->file_path;

        // Delete the DB row first; the physical file is only removed after
        // the transaction is confirmed, so a failed deletion never destroys
        // the document while its record survives (issue #46 constraint).
        DB::transaction(fn () => $version->delete());

        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        return response()->json(['message' => 'Versión eliminada correctamente.']);
    }

    /**
     * PUT /api/admin/entregas/{id}/revisar
     *
     * Director aprueba/rechaza entrega con nota y feedback.
     *
     * Pass `proyecto=<id>` to grade the delivery of ONE project: the verdict
     * and the grade are written to that project's EntregaProyecto pivot and the
     * semester-wide template is left untouched. Without it the review keeps
     * its legacy global behaviour.
     */
    public function revisar(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        // Issue #38: only the director of a linked project reviews.
        $this->authorize('review', $entrega);

        $validator = Validator::make($request->all(), [
            'proyecto' => 'nullable|integer',
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

        // Resolve the project delivery BEFORE any write. Without this the
        // review is applied to the semester template on behalf of every
        // project, and a version belonging to a DIFFERENT project can be
        // graded while the screen claims to be reviewing another one. 404
        // (not 403) once the pivot exists: it must not confirm which other
        // projects of this entrega own a version.
        $entregaProyecto = $this->resolverPivoteDeProyecto(
            $entrega,
            $this->proyectoSolicitado($request),
            $request->user(),
        );

        // RF-NOT-01 / D7: director_grade range (0-5) and max 2 decimals,
        // validated only when the review approves (RF-NOT-02).
        // New contract: the grade is REQUIRED when approving; it stays
        // optional (nullable) for rechazada/revisada.
        if (($data['status'] ?? null) === 'aprobada') {
            if (! isset($data['director_grade'])) {
                return $this->errorEnvelope(422, 'La nota del director es obligatoria al aprobar la entrega');
            }

            $grade = (float) $data['director_grade'];

            if ($grade < 0 || $grade > 5) {
                return $this->errorEnvelope(422, 'La nota del director debe estar entre 0 y 5');
            }

            if (round($grade, 2) !== $grade) {
                return $this->errorEnvelope(422, 'La nota del director debe tener máximo 2 decimales');
            }
        }

        try {
            $entrega = $this->reviewEntregaAction->handle(
                $entrega,
                $data,
                $request->user()->id,
                $entregaProyecto,
            );
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
        $this->authorize('manage', Entrega::class);

        $query = Entrega::where('status', 'aprobada')
            ->with([
                'proyectos:id,code,title,director_id',
                'versiones' => fn ($q) => $q->latest(),
            ]);

        if ($request->filled('proyecto_id')) {
            $query->whereHas('proyectos', fn ($sq) => $sq->where('proyecto_id', $request->integer('proyecto_id')));
        }

        if ($request->filled('fecha_desde')) {
            $query->where('due_date', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('due_date', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('director_id')) {
            $query->whereHas('proyectos', fn ($q) => $q->where('director_id', $request->integer('director_id')));
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
}
