<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoEntrega;
use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntregaRequest;
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
            'start_date' => 'sometimes|nullable|date|before_or_equal:due_date',
            'start_time' => 'sometimes|nullable|string|max:10',
            'phase' => 'sometimes|required|string|max:50',
            'proyecto_id' => 'sometimes|required|exists:proyectos,id',
            'archivos_requeridos' => 'sometimes|required|array|min:1|max:6',
            'archivos_requeridos.*.id' => 'required_with:archivos_requeridos|string|max:50|regex:/^[a-z0-9_]+$/',
            'archivos_requeridos.*.nombre' => 'required_with:archivos_requeridos|string|max:255',
            'archivos_requeridos.*.versionamiento' => 'required_with:archivos_requeridos|boolean',
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
        if (array_key_exists('start_date', $data)) {
            $entrega->start_date = $data['start_date'];
        }
        if (array_key_exists('start_time', $data)) {
            $entrega->start_time = $data['start_time'];
        }
        if (isset($data['phase'])) {
            $entrega->phase = $data['phase'];
        }
        if (isset($data['proyecto_id'])) {
            $entrega->proyecto_id = $data['proyecto_id'];
        }

        // Handle archivos_requeridos update with versioning validation
        if (isset($data['archivos_requeridos'])) {
            $nuevosArchivos = collect($data['archivos_requeridos'])->map(function (array $item) {
                return [
                    'slug' => $item['id'],
                    'nombre' => $item['nombre'],
                    'versionamiento' => (bool) $item['versionamiento'],
                ];
            });

            // Validate: if a file had versionamiento=true and has existing versions,
            // don't allow changing versionamiento to false
            $actuales = $entrega->archivos_requeridos ?? [];
            foreach ($actuales as $actual) {
                $nuevo = $nuevosArchivos->firstWhere('slug', $actual['slug'] ?? $actual['id'] ?? null);
                if ($nuevo && ($actual['versionamiento'] ?? false) === true && $nuevo['versionamiento'] === false) {
                    // Check if there are existing versions for this archivo_requerido
                    $tieneVersiones = $entrega->versiones()
                        ->where('archivo_requerido_id', $actual['slug'] ?? $actual['id'] ?? null)
                        ->exists();

                    if ($tieneVersiones) {
                        return response()->json([
                            'error' => "No se puede deshabilitar el versionamiento para '{$actual['nombre']}' porque ya tiene versiones subidas.",
                        ], 422);
                    }
                }
            }

            // Check unique IDs
            $ids = $nuevosArchivos->pluck('slug')->toArray();
            if (count($ids) !== count(array_unique($ids))) {
                return response()->json([
                    'error' => 'Los IDs de los archivos requeridos deben ser únicos.',
                ], 422);
            }

            $entrega->archivos_requeridos = $nuevosArchivos->toArray();
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
    public function store(StoreEntregaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $archivos = collect($data['archivos_requeridos'])->map(function (array $item) {
            return [
                'slug' => $item['id'],
                'nombre' => $item['nombre'],
                'versionamiento' => (bool) $item['versionamiento'],
            ];
        })->toArray();

        unset($data['archivos_requeridos']);

        $entrega = Entrega::create([
            'semester_id' => $data['grupo_id'],
            'phase' => $data['fase'],
            'title' => $data['titulo'],
            'description' => $data['descripcion'],
            'due_date' => $data['fecha_limite'],
            'start_date' => $data['fecha_inicio'] ?? null,
            'start_time' => $data['hora_inicio'] ?? null,
            'hora_maxima' => $data['hora_maxima'] ?? null,
            'acceptance_criteria' => $data['criterios'] ?? null,
            'status' => 'pendiente',
            'archivos_requeridos' => $archivos,
        ]);

        // Vincular a todos los proyectos activos del semestre
        $proyectos = Proyecto::where('semester_id', $data['grupo_id'])
            ->whereIn('status', [EstadoProyecto::EnCurso->value, EstadoProyecto::EnRiesgo->value, EstadoProyecto::Completado->value])
            ->pluck('id');

        $entrega->proyectos()->attach($proyectos);
        $entrega->load('semestre:id,name');

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

        // Auto-promote to pendiente if it's in a pre-upload status
        if (! in_array($entrega->status->value, [EstadoEntrega::Pendiente->value, EstadoEntrega::Enviada->value], true)) {
            $entrega->update(['status' => EstadoEntrega::Pendiente->value]);
        }

        // Verify submission is within the allowed time window
        $now = now();
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Check start date constraint
        if ($entrega->start_date) {
            $startDate = $entrega->start_date instanceof \Carbon\Carbon
                ? $entrega->start_date->format('Y-m-d')
                : $entrega->start_date;

            if ($today < $startDate) {
                return response()->json([
                    'error' => 'La entrega aún no está disponible. La fecha de inicio es ' . $startDate . '.',
                ], 422);
            }

            // If today is the start date and there's a start time, check time
            if ($today === $startDate && $entrega->start_time && $currentTime < $entrega->start_time) {
                return response()->json([
                    'error' => 'La entrega aún no está disponible. La hora de inicio es las ' . $entrega->start_time . '.',
                ], 422);
            }
        }

        // Check due date constraint
        $dueDate = $entrega->due_date instanceof \Carbon\Carbon
            ? $entrega->due_date->format('Y-m-d')
            : $entrega->due_date;

        if ($today > $dueDate) {
            return response()->json([
                'error' => 'La fecha límite de la entrega ya pasó (' . $dueDate . ').',
            ], 422);
        }

        // If today is the due date and there's a hora_maxima, check time
        if ($today === $dueDate && $entrega->hora_maxima && $currentTime > $entrega->hora_maxima) {
            return response()->json([
                'error' => 'La hora máxima para esta entrega era las ' . $entrega->hora_maxima . '.',
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

        // Store file on the public disk so it's accessible via /storage/ URL
        $path = $file->storeAs(
            "entregas/{$id}",
            "v{$newVersionNumber}_{$originalName}",
            'public'
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

        // Verify submission is within the allowed time window
        $now = now();
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Check start date constraint
        if ($entrega->start_date) {
            $startDate = $entrega->start_date instanceof \Carbon\Carbon
                ? $entrega->start_date->format('Y-m-d')
                : $entrega->start_date;

            if ($today < $startDate) {
                return response()->json([
                    'error' => 'La entrega aún no está disponible. La fecha de inicio es ' . $startDate . '.',
                ], 422);
            }

            if ($today === $startDate && $entrega->start_time && $currentTime < $entrega->start_time) {
                return response()->json([
                    'error' => 'La entrega aún no está disponible. La hora de inicio es las ' . $entrega->start_time . '.',
                ], 422);
            }
        }

        // Check due date constraint
        $dueDate = $entrega->due_date instanceof \Carbon\Carbon
            ? $entrega->due_date->format('Y-m-d')
            : $entrega->due_date;

        if ($today > $dueDate) {
            return response()->json([
                'error' => 'La fecha límite de la entrega ya pasó (' . $dueDate . ').',
            ], 422);
        }

        if ($today === $dueDate && $entrega->hora_maxima && $currentTime > $entrega->hora_maxima) {
            return response()->json([
                'error' => 'La hora máxima para esta entrega era las ' . $entrega->hora_maxima . '.',
            ], 422);
        }

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

        $data = $entrega->toArray();
        $data['proyectos_count'] = $entrega->proyectos->count();
        $data['versiones_count'] = $entrega->versiones->count();

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

        // Save director notes to the specific version sent by the frontend
        if (! empty($data['director_notes'])) {
            $version = VersionDocumento::where('entrega_id', $id)
                ->where('id', $data['version_id'])
                ->firstOrFail();

            $version->update(['director_notes' => $data['director_notes']]);
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
