<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoEntrega;
use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\Proyecto;
use App\Models\VersionDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    private const MAX_VERSIONS = 4;

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
    public function update(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role->value !== 'Coordinador') {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'due_date' => 'sometimes|required|date',
            'description' => 'sometimes|required|string|max:500',
            'titulo' => 'sometimes|required|string|max:255',
            'acceptance_criteria' => 'sometimes|nullable|string',
            'hora_maxima' => 'sometimes|nullable|string|max:10',
            'phase' => 'sometimes|required|string|max:50',
            'proyecto_id' => 'sometimes|required|exists:proyectos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entrega = Entrega::findOrFail($id);
        $data = $validator->validated();

        if (isset($data['due_date'])) {
            $entrega->due_date = $data['due_date'];
        }
        if (isset($data['description'])) {
            $entrega->description = $data['description'];
        }
        if (isset($data['titulo'])) {
            $entrega->title = $data['titulo'];
        }
        if (array_key_exists('acceptance_criteria', $data)) {
            $entrega->acceptance_criteria = $data['acceptance_criteria'];
        }
        if (array_key_exists('hora_maxima', $data)) {
            $entrega->hora_maxima = $data['hora_maxima'];
        }
        if (isset($data['phase'])) {
            $entrega->phase = $data['phase'];
        }
        if (isset($data['proyecto_id'])) {
            $entrega->proyecto_id = $data['proyecto_id'];
        }
        $entrega->save();

        $entrega->load('proyecto:id,code,title,semester_id', 'proyecto.semestre:id,name', 'proyectos:id,code,title');

        $arr = $entrega->toArray();
        $arr['semestre_nombre'] = $entrega->proyecto?->semestre?->name ?? '—';
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
    public function store(Request $request): JsonResponse
    {
        // Only coordinators can create entregas
        if ($request->user()->role->value !== 'Coordinador') {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:semestres,id',
            'fase' => 'required|string|max:50',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string|max:500',
            'fecha_limite' => 'required|date',
            'criterios' => 'nullable|string',
            'hora_maxima' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $entrega = Entrega::create([
            'semester_id' => $data['grupo_id'],
            'phase' => $data['fase'],
            'title' => $data['titulo'],
            'description' => $data['descripcion'],
            'due_date' => $data['fecha_limite'],
            'hora_maxima' => $data['hora_maxima'] ?? null,
            'acceptance_criteria' => $data['criterios'] ?? null,
            'status' => EstadoEntrega::Creada->value,
        ]);

        // Link to all active projects in the semester
        $proyectos = Proyecto::where('semester_id', $data['grupo_id'])
            ->whereIn('status', [EstadoProyecto::EnCurso->value, EstadoProyecto::EnRiesgo->value, EstadoProyecto::Completado->value])
            ->pluck('id');

        $entrega->proyectos()->attach($proyectos);

        $entrega->load('semestre:id,name', 'proyectos:id,code,title');

        return response()->json(['data' => $entrega], 201);
    }

    /**
     * POST /api/entregas/{id}/versiones
     *
     * Subir una versión de documento a una entrega (estudiante del proyecto).
     */
    public function subirVersion(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        // Verify the entrega has been habilitated by the director
        $allowedStatuses = [EstadoEntrega::Pendiente->value, EstadoEntrega::Enviada->value];
        if (! in_array($entrega->status->value, $allowedStatuses, true)) {
            return response()->json([
                'error' => 'La entrega no está habilitada para recibir versiones.',
            ], 422);
        }

        // Verify the user is a student of any linked project
        $userId = $request->user()->id;
        $esEstudiante = $this->esEstudianteDeEntrega($entrega, $userId);

        if (! $esEstudiante) {
            return response()->json(['error' => 'No eres estudiante de este proyecto.'], 403);
        }

        // Max 4 versions
        $currentCount = VersionDocumento::where('entrega_id', $id)->count();
        if ($currentCount >= self::MAX_VERSIONS) {
            return response()->json([
                'error' => 'Máximo ' . self::MAX_VERSIONS . ' versiones por entrega.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
                'max:' . (self::MAX_FILE_SIZE / 1024),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        // Auto-increment version number
        $lastVersion = VersionDocumento::where('entrega_id', $id)
            ->max('version_number');

        $newVersionNumber = ($lastVersion ?? 0) + 1;

        // Store file
        $path = $file->storeAs(
            "entregas/{$id}",
            "v{$newVersionNumber}_{$originalName}",
            'local'
        );

        $version = VersionDocumento::create([
            'entrega_id' => $id,
            'version_number' => $newVersionNumber,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'original_name' => $originalName,
            'uploaded_at' => now(),
        ]);

        // Update entrega status to 'enviada'
        $entrega->update(['status' => 'enviada']);

        return response()->json(['data' => $version], 201);
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
        $esEstudiante = $this->esEstudianteDeEntrega($entrega, $user->id);

        if (! $esEstudiante) {
            return response()->json(['error' => 'No eres estudiante de este proyecto.'], 403);
        }

        if ($entrega->status->value !== EstadoEntrega::Creada->value) {
            return response()->json([
                'error' => 'La entrega no está en estado de creación.',
            ], 422);
        }

        $entrega->update(['status' => EstadoEntrega::Solicitada->value]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'entrega.solicitar',
            'description' => "Estudiante solicitó habilitación para entrega #{$entrega->id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'entrega_id' => $entrega->id,
                'proyecto_id' => $entrega->proyecto_id,
            ],
        ]);

        $entrega->load('proyecto:id,code,title');

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

        return response()->json(['data' => $entrega]);
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

        if ($entrega->status->value !== EstadoEntrega::Solicitada->value) {
            return response()->json([
                'error' => 'La entrega no está en estado solicitada.',
            ], 422);
        }

        $entrega->update(['status' => EstadoEntrega::Pendiente->value]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'entrega.habilitar',
            'description' => "Director habilitó entrega #{$entrega->id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'entrega_id' => $entrega->id,
                'proyecto_id' => $entrega->proyecto_id,
            ],
        ]);

        $entrega->load('proyecto:id,code,title');

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
            'status' => 'required|string|in:aprobada,rechazada',
            'consolidated_grade' => 'nullable|numeric|min:0|max:5',
            'director_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $entrega->update([
            'status' => $data['status'],
            'consolidated_grade' => $data['consolidated_grade'] ?? null,
            'evaluation_complete' => true,
        ]);

        // If approved, add notes to the latest version
        if ($data['status'] === 'aprobada' && ! empty($data['director_notes'])) {
            $latestVersion = VersionDocumento::where('entrega_id', $id)
                ->orderByDesc('version_number')
                ->first();

            if ($latestVersion) {
                $latestVersion->update(['director_notes' => $data['director_notes']]);
            }
        }

        // Auto-advance phase if all entregas in the current phase are approved
        if ($data['status'] === 'aprobada') {
            $this->autoAdvancePhase($entrega);
        }

        $entrega->load('proyecto:id,code,title,current_phase', 'proyectos:id,code,title,current_phase');

        // T-022: Notificar a los estudiantes de todos los proyectos vinculados
        $proyectos = $entrega->proyectos->isNotEmpty() ? $entrega->proyectos : collect([$entrega->proyecto])->filter();
        $notifiedUserIds = collect();
        foreach ($proyectos as $proyecto) {
            $estudiantes = $proyecto->estudiantes()->pluck('user_id');
            foreach ($estudiantes as $estudianteId) {
                if ($notifiedUserIds->has($estudianteId)) {
                    continue;
                }
                $notifiedUserIds->put($estudianteId, true);
                Notificacion::create([
                    'user_id' => $estudianteId,
                    'sender_id' => $user->id,
                    'type' => 'entrega.revisada',
                    'title' => "Entrega {$data['status']}: {$entrega->title}",
                    'content' => "Tu entrega '{$entrega->title}' ha sido {$data['status']}.",
                    'sent_at' => now(),
                ]);
            }
        }

        return response()->json(['data' => $entrega->fresh()]);
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
     * Auto-advance the project phase when all entregas in the current
     * phase are approved.
     */
    private function autoAdvancePhase(Entrega $entrega): void
    {
        $proyectos = $entrega->proyectos()->get();

        // Fall back to direct proyecto relation if no pivot projects
        if ($proyectos->isEmpty() && $entrega->proyecto) {
            $proyectos = collect([$entrega->proyecto]);
        }

        foreach ($proyectos as $proyecto) {
            // Check if there are any non-approved entregas in this phase for this project
            // (scope checks both direct FK and pivot table)
            $pendingInPhase = Entrega::paraProyecto($proyecto->id)
                ->where('phase', $entrega->phase)
                ->where('status', '!=', 'aprobada')
                ->exists();

            if (! $pendingInPhase) {
                $currentPhase = $proyecto->current_phase;

                if ($currentPhase->value === $entrega->phase) {
                    $nextPhase = $currentPhase->next();
                    if ($nextPhase !== null) {
                        $proyecto->current_phase = $nextPhase;
                        $proyecto->save();
                    }
                }
            }
        }
    }

    /**
     * Check if a user is a student of any project linked to this entrega.
     */
    private function esEstudianteDeEntrega(Entrega $entrega, int $userId): bool
    {
        // Check via pivot projects
        $proyectos = $entrega->proyectos()->get();
        foreach ($proyectos as $proyecto) {
            $esEstudiante = $proyecto->estudiantes()
                ->where('user_id', $userId)
                ->exists();
            if ($esEstudiante) {
                return true;
            }
        }

        // Fall back to direct proyecto relation
        if ($entrega->proyecto) {
            return $entrega->proyecto->estudiantes()
                ->where('user_id', $userId)
                ->exists();
        }

        return false;
    }

    /**
     * Check if a user is the director of any project linked to this entrega.
     */
    private function esDirectorDeEntrega(Entrega $entrega, int $userId): bool
    {
        // Check via pivot projects
        $proyectos = $entrega->proyectos()->get();
        foreach ($proyectos as $proyecto) {
            if ($proyecto->director_id === $userId) {
                return true;
            }
        }

        // Fall back to direct proyecto relation
        if ($entrega->proyecto && $entrega->proyecto->director_id === $userId) {
            return true;
        }

        return false;
    }
}
