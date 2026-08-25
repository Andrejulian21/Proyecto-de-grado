<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EstadoEntrega;
use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\VersionDocumento;
use App\Services\Evaluation\AttachTemporaryAiFeedbackToVersion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EntregaEstudianteController extends Controller
{
    public function __construct(
        private readonly AttachTemporaryAiFeedbackToVersion $attachTemporaryAiFeedback,
    ) {}

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    private const MAX_VERSIONS = 4;

    /**
     * POST /api/entregas/{entrega}/archivos/{slug}
     *
     * Subir un archivo para un archivo requerido específico.
     */
    public function subirArchivoPorSlug(Request $request, int $entregaId, string $slug): JsonResponse
    {
        $user = $request->user();

        // 1. Buscar entrega
        $entrega = Entrega::findOrFail($entregaId);

        // 2. Buscar el archivo requerido en archivos_requeridos por slug
        $archivoRequerido = $this->findArchivoRequerido($entrega, $slug);

        if ($archivoRequerido === null) {
            return response()->json(['error' => 'Archivo requerido no encontrado.'], 404);
        }

        // 3. Obtener el proyecto del estudiante autenticado
        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->first();

        if (! $proyecto) {
            return response()->json(['error' => 'No tienes un proyecto asignado.'], 404);
        }

        // Verificar que la entrega está vinculada al proyecto
        $tieneVinculo = $entrega->proyectos()->where('proyecto_id', $proyecto->id)->exists();

        if (! $tieneVinculo) {
            return response()->json(['error' => 'Esta entrega no está asignada a tu proyecto.'], 403);
        }

        // Auto-promote to pendiente if needed
        if (! in_array($entrega->status->value, [EstadoEntrega::Pendiente->value, EstadoEntrega::Enviada->value], true)) {
            $entrega->update(['status' => EstadoEntrega::Pendiente->value]);
        }

        // Verify time window
        $timeCheck = $this->verificarVentanaTiempo($entrega);

        if ($timeCheck !== null) {
            return $timeCheck;
        }

        // 4. Buscar o crear el pivot entrega_proyecto
        $pivot = EntregaProyecto::firstOrCreate(
            [
                'entrega_id' => $entrega->id,
                'proyecto_id' => $proyecto->id,
            ],
            [
                'estado' => 'pendiente',
            ]
        );

        // Validate: MAX_VERSIONS per archivo_requerido
        $currentCount = VersionDocumento::where('entrega_proyecto_id', $pivot->id)
            ->where('archivo_requerido_id', $slug)
            ->where('descontinuado', false)
            ->count();

        if ($currentCount >= self::MAX_VERSIONS) {
            return response()->json([
                'error' => 'Máximo '.self::MAX_VERSIONS.' versiones por archivo requerido.',
            ], 422);
        }

        // Validate file
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
                'max:'.(self::MAX_FILE_SIZE / 1024),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        $versionamiento = (bool) ($archivoRequerido['versionamiento'] ?? false);

        // 5. Si versionamiento = true: crear nueva VersionDocumento
        // 6. Si versionamiento = false: eliminar versiones existentes, luego crear nueva
        if (! $versionamiento) {
            VersionDocumento::where('entrega_proyecto_id', $pivot->id)
                ->where('archivo_requerido_id', $slug)
                ->where('descontinuado', false)
                ->update(['descontinuado' => true]);
        }

        // Auto-increment version number per archivo_requerido + project
        $lastVersion = VersionDocumento::where('entrega_proyecto_id', $pivot->id)
            ->where('archivo_requerido_id', $slug)
            ->max('version_number');

        $newVersionNumber = ($lastVersion ?? 0) + 1;

        // Server-side file name (issue #56, defect 2): the base is normalized
        // with a slug, the extension is derived from the actual detected type
        // (already validated by the `mimetypes` whitelist), and a timestamp is
        // appended for uniqueness. The client-provided name is preserved only
        // in the `original_name` column below.
        $storedBase = Str::slug(pathinfo((string) $originalName, PATHINFO_FILENAME));
        $storedName = sprintf(
            'v%d_%s_%s.%s',
            $newVersionNumber,
            $storedBase !== '' ? $storedBase : 'documento',
            now()->format('Ymd_His'),
            $file->guessExtension() ?: 'pdf'
        );

        // Store file
        $path = $file->storeAs(
            "entregas/{$entrega->id}/{$slug}",
            $storedName,
            'public'
        );

        $version = VersionDocumento::create([
            'entrega_id' => $entrega->id,
            'entrega_proyecto_id' => $pivot->id,
            'archivo_requerido_id' => $slug,
            'version_number' => $newVersionNumber,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'original_name' => $originalName,
            'uploaded_at' => now(),
            'descontinuado' => false,
        ]);

        $this->attachTemporaryAiFeedback->handle($version);

        // Update entrega status to 'enviada'
        $entrega->update(['status' => EstadoEntrega::Enviada->value]);

        return response()->json(['data' => $version], 201);
    }

    /**
     * GET /api/entregas/{entrega}/estado
     *
     * Estado de completitud de los archivos requeridos.
     */
    public function estadoCompletitud(Request $request, int $entregaId): JsonResponse
    {
        $user = $request->user();

        // 1. Obtener proyecto del estudiante
        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->first();

        if (! $proyecto) {
            return response()->json(['error' => 'No tienes un proyecto asignado.'], 404);
        }

        // 2. Buscar entrega
        $entrega = Entrega::findOrFail($entregaId);

        // 3. Buscar pivot entrega_proyecto
        $pivot = EntregaProyecto::where('entrega_id', $entrega->id)
            ->where('proyecto_id', $proyecto->id)
            ->first();

        $archivosRequeridos = $entrega->archivos_requeridos ?? [];

        $detalle = [];
        $completos = 0;
        $pendientes = 0;

        foreach ($archivosRequeridos as $archivo) {
            $slug = $archivo['slug'] ?? $archivo['id'] ?? null;
            $nombre = $archivo['nombre'] ?? '—';

            if ($slug === null) {
                continue;
            }

            $tieneVersion = false;

            if ($pivot) {
                $tieneVersion = VersionDocumento::where('entrega_proyecto_id', $pivot->id)
                    ->where('archivo_requerido_id', $slug)
                    ->where('descontinuado', false)
                    ->exists();
            }

            $detalle[] = [
                'slug' => $slug,
                'nombre' => $nombre,
                'versionamiento' => (bool) ($archivo['versionamiento'] ?? false),
                'completado' => $tieneVersion,
                'ultima_version' => $tieneVersion
                    ? VersionDocumento::where('entrega_proyecto_id', $pivot->id)
                        ->where('archivo_requerido_id', $slug)
                        ->where('descontinuado', false)
                        ->max('version_number')
                    : null,
            ];

            if ($tieneVersion) {
                $completos++;
            } else {
                $pendientes++;
            }
        }

        return response()->json([
            'data' => [
                'entrega_id' => $entrega->id,
                'entrega_titulo' => $entrega->title,
                'total_archivos' => count($archivosRequeridos),
                'completos' => $completos,
                'pendientes' => $pendientes,
                'detalle' => $detalle,
            ],
        ]);
    }

    /**
     * Find an archivo_requerido by its slug in the entrega's stored JSON.
     *
     * @return array<string, mixed>|null
     */
    private function findArchivoRequerido(Entrega $entrega, string $slug): ?array
    {
        $archivos = $entrega->archivos_requeridos ?? [];

        if (! is_array($archivos)) {
            return null;
        }

        foreach ($archivos as $archivo) {
            $id = $archivo['slug'] ?? $archivo['id'] ?? null;

            if ($id === $slug) {
                return $archivo;
            }
        }

        return null;
    }

    /**
     * Verify the submission is within the allowed date/time window.
     * Returns a JsonResponse with error if outside window, or null if OK.
     */
    private function verificarVentanaTiempo(Entrega $entrega): ?JsonResponse
    {
        $now = now();
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Check start date constraint
        if ($entrega->start_date) {
            $startDate = $entrega->start_date instanceof Carbon
                ? $entrega->start_date->format('Y-m-d')
                : $entrega->start_date;

            if ($today < $startDate) {
                return response()->json([
                    'error' => 'La entrega aún no está disponible. La fecha de inicio es '.$startDate.'.',
                ], 422);
            }

            if ($today === $startDate && $entrega->start_time && $currentTime < $entrega->start_time) {
                return response()->json([
                    'error' => 'La entrega aún no está disponible. La hora de inicio es las '.$entrega->start_time.'.',
                ], 422);
            }
        }

        // Check due date constraint
        $dueDate = $entrega->due_date instanceof Carbon
            ? $entrega->due_date->format('Y-m-d')
            : $entrega->due_date;

        if ($today > $dueDate) {
            return response()->json([
                'error' => 'La fecha límite de la entrega ya pasó ('.$dueDate.').',
            ], 422);
        }

        if ($today === $dueDate && $entrega->hora_maxima && $currentTime > $entrega->hora_maxima) {
            return response()->json([
                'error' => 'La hora máxima para esta entrega era las '.$entrega->hora_maxima.'.',
            ], 422);
        }

        return null;
    }
}
