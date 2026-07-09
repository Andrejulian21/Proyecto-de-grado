<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoEntrega;
use App\Enums\FaseProyecto;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Entrega;
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
        $query = Entrega::query()->with('proyecto:id,code,title');

        // Role-based scoping
        if ($user->role->value === 'Director') {
            $query->whereHas('proyecto', fn ($q) => $q->where('director_id', $user->id));
        } elseif ($user->role->value === 'Estudiante') {
            $query->whereHas('proyecto.estudiantes', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('proyecto_id')) {
            $query->where('proyecto_id', $request->integer('proyecto_id'));
        }

        if ($request->filled('fase')) {
            $query->where('phase', $request->input('fase'));
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->get(),
        ]);
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
            'proyecto_id' => 'required|exists:proyectos,id',
            'phase' => 'required|string|max:50',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $entrega = Entrega::create([
            'proyecto_id' => $data['proyecto_id'],
            'phase' => $data['phase'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'],
            'status' => EstadoEntrega::Creada->value,
        ]);

        $entrega->load('proyecto:id,code,title');

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

        // Verify the user is a student of this project
        $userId = $request->user()->id;
        $esEstudiante = $entrega->proyecto->estudiantes()
            ->where('user_id', $userId)
            ->exists();

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
        $esEstudiante = $entrega->proyecto->estudiantes()
            ->where('user_id', $user->id)
            ->exists();

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
     * PUT /api/admin/entregas/{id}/habilitar
     *
     * Director habilita la entrega para que el estudiante suba versiones.
     */
    public function habilitar(Request $request, int $id): JsonResponse
    {
        $entrega = Entrega::findOrFail($id);

        $user = $request->user();
        if ($entrega->proyecto->director_id !== $user->id) {
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
            $esEstudiante = $entrega->proyecto->estudiantes()
                ->where('user_id', $user->id)
                ->exists();

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

        // Verify the user is the director of this project
        $user = $request->user();
        if ($entrega->proyecto->director_id !== $user->id) {
            return response()->json(['error' => 'No eres el director de este proyecto.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:aprobada,rechazada',
            'consolidated_grade' => 'nullable|numeric|min:0|max:100',
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

        $entrega->load('proyecto:id,code,title,current_phase');

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
            ->with(['proyecto:id,code,title,director_id', 'versiones' => fn ($q) => $q->latest()]);

        if ($request->filled('proyecto_id')) {
            $query->where('proyecto_id', $request->integer('proyecto_id'));
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
        $proyecto = $entrega->proyecto()->firstOrFail();

        // Check if there are any non-approved entregas in this phase
        $pendingInPhase = Entrega::where('proyecto_id', $proyecto->id)
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
