<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EstadoFirma;
use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Notificacion;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BitacoraController extends Controller
{
    /**
     * GET /api/bitacoras?proyecto_id=
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $proyectoId = (int) $request->input('proyecto_id');
        $user = $request->user();

        if (! $this->tieneAccesoAProyecto($user, $proyectoId)) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $bitacoras = Bitacora::where('proyecto_id', $proyectoId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $bitacoras]);
    }

    /**
     * POST /api/bitacoras
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
            'topic' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'evidence_file' => 'nullable|string|max:500',
            'meeting_date' => 'required|date',
            'duration_hours' => 'nullable|numeric|min:0|max:999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        if (! $this->tieneAccesoAProyecto($user, (int) $data['proyecto_id'])) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $data['signature_status'] = EstadoFirma::Pendiente->value;

        $bitacora = Bitacora::create($data);

        return response()->json(['data' => $bitacora], 201);
    }

    /**
     * GET /api/bitacoras/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $bitacora = Bitacora::findOrFail($id);

        if (! $this->tieneAccesoAProyecto($request->user(), $bitacora->proyecto_id)) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        return response()->json(['data' => $bitacora]);
    }

    /**
     * PUT /api/bitacoras/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bitacora = Bitacora::findOrFail($id);

        if (! $this->tieneAccesoAProyecto($request->user(), $bitacora->proyecto_id)) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if ($bitacora->signature_status->value !== EstadoFirma::Pendiente->value) {
            return response()->json([
                'error' => 'No se puede modificar una bitácora que ya fue firmada.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'sometimes|string|max:500',
            'notes' => 'nullable|string',
            'evidence_file' => 'nullable|string|max:500',
            'meeting_date' => 'sometimes|date',
            'duration_hours' => 'nullable|numeric|min:0|max:999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bitacora->update($validator->validated());

        return response()->json(['data' => $bitacora->fresh()]);
    }

    /**
     * POST /api/bitacoras/{id}/firmar
     *
     * Flow: Pendiente -> estudiante firma -> FirmadaEstudiante
     *       FirmadaEstudiante -> director firma -> Completada
     */
    public function firmar(Request $request, int $id): JsonResponse
    {
        $bitacora = Bitacora::findOrFail($id);
        $user = $request->user();
        $proyecto = Proyecto::findOrFail($bitacora->proyecto_id);

        $currentStatus = $bitacora->signature_status->value;

        if ($currentStatus === EstadoFirma::Completada->value) {
            return response()->json(['error' => 'La bitácora ya está completamente firmada.'], 422);
        }

        if ($currentStatus === EstadoFirma::Pendiente->value) {
            // T-009: Director can sign directly from Pendiente → Completada
            if ($proyecto->director_id === $user->id) {
                $bitacora->update([
                    'signature_status' => EstadoFirma::Completada->value,
                    'director_signed_at' => now(),
                ]);

                // Notificar a los estudiantes del proyecto
                $estudiantes = $proyecto->estudiantes()->pluck('user_id');
                foreach ($estudiantes as $estudianteId) {
                    Notificacion::create([
                        'user_id' => $estudianteId,
                        'sender_id' => $user->id,
                        'type' => 'bitacora.completada',
                        'title' => 'Bitácora completada por director',
                        'content' => "El director ha completado la firma de la bitácora '{$bitacora->topic}'.",
                        'sent_at' => now(),
                    ]);
                }

                return response()->json(['data' => $bitacora->fresh()]);
            }

            // Only a student of this project can sign from Pendiente
            $esEstudiante = $proyecto->estudiantes()
                ->where('user_id', $user->id)
                ->exists();

            if (! $esEstudiante) {
                return response()->json(['error' => 'No eres estudiante de este proyecto.'], 403);
            }

            $bitacora->update([
                'signature_status' => EstadoFirma::FirmadaEstudiante->value,
                'student_signed_at' => now(),
            ]);

            // T-022: Notificar al director
            if ($proyecto->director_id) {
                Notificacion::create([
                    'user_id' => $proyecto->director_id,
                    'sender_id' => $user->id,
                    'type' => 'bitacora.firmada_estudiante',
                    'title' => "Bitácora firmada por estudiante",
                    'content' => "El estudiante ha firmado la bitácora '{$bitacora->topic}'.",
                    'sent_at' => now(),
                ]);
            }

            return response()->json(['data' => $bitacora->fresh()]);
        }

        if ($currentStatus === EstadoFirma::FirmadaEstudiante->value ||
            $currentStatus === EstadoFirma::FirmadaDirector->value) {
            // Only the director can sign from FirmadaEstudiante
            if ($proyecto->director_id !== $user->id) {
                return response()->json(['error' => 'No eres el director de este proyecto.'], 403);
            }

            $bitacora->update([
                'signature_status' => EstadoFirma::Completada->value,
                'director_signed_at' => now(),
            ]);

            // T-013: detect suspicious rapid signatures (director signs >3 in 5 min)
            $this->detectarFirmasSospechosas($proyecto, $user);

            // T-022: Notificar a los estudiantes del proyecto
            $estudiantes = $proyecto->estudiantes()->pluck('user_id');
            foreach ($estudiantes as $estudianteId) {
                Notificacion::create([
                    'user_id' => $estudianteId,
                    'sender_id' => $user->id,
                    'type' => 'bitacora.completada',
                    'title' => "Bitácora completada por director",
                    'content' => "El director ha completado la firma de la bitácora '{$bitacora->topic}'.",
                    'sent_at' => now(),
                ]);
            }

            return response()->json(['data' => $bitacora->fresh()]);
        }

        return response()->json(['error' => 'Estado de firma no válido para esta acción.'], 422);
    }

    /**
     * GET /api/admin/proyectos/{proyecto}/bitacoras
     *
     * Devuelve las bitácoras de un proyecto para la vista de directores.
     * Accesible solo por coordinadores (ruta en grupo admin).
     */
    public function porProyecto(int $proyectoId): JsonResponse
    {
        $proyecto = Proyecto::with('director')->findOrFail($proyectoId);

        $bitacoras = Bitacora::where('proyecto_id', $proyectoId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($bitacora) use ($proyecto) {
                return [
                    'id' => $bitacora->id,
                    'fecha' => $bitacora->created_at->toISO8601String(),
                    'contenido' => $bitacora->notes ?? '',
                    'firmada' => $bitacora->signature_status->value === EstadoFirma::Completada->value,
                    'director_name' => $proyecto->director?->name ?? 'Sin asignar',
                ];
            });

        return response()->json(['data' => $bitacoras]);
    }

    /**
     * GET /api/director/proyectos/{id}/horas
     *
     * T-014: Total accumulated bitácora hours for a project.
     * Available to the project's director and coordinators.
     */
    public function horas(Request $request, int $id): JsonResponse
    {
        $proyecto = Proyecto::findOrFail($id);
        $user = $request->user();

        // Only the project's director or a coordinator can view hours
        $esCoordinador = $user->role->value === 'Coordinador';
        $esDirector = $proyecto->director_id === $user->id;

        if (! $esCoordinador && ! $esDirector) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $totalHoras = (float) Bitacora::where('proyecto_id', $id)
            ->sum('duration_hours');

        $totalBitacoras = Bitacora::where('proyecto_id', $id)->count();

        return response()->json([
            'total_horas' => round($totalHoras, 2),
            'total_bitacoras' => $totalBitacoras,
            'proyecto_id' => $id,
        ]);
    }

    /**
     * T-013: If the director signs >3 bitácoras in a 5-minute window,
     * mark all of them as Sospechosa.
     */
    private function detectarFirmasSospechosas($proyecto, $user): void
    {
        $fiveMinutesAgo = now()->subMinutes(5);

        $firmasRecientes = Bitacora::where('proyecto_id', $proyecto->id)
            ->whereNotNull('director_signed_at')
            ->where('director_signed_at', '>=', $fiveMinutesAgo)
            ->count();

        if ($firmasRecientes > 3) {
            Bitacora::where('proyecto_id', $proyecto->id)
                ->whereNotNull('director_signed_at')
                ->where('director_signed_at', '>=', $fiveMinutesAgo)
                ->update(['signature_status' => EstadoFirma::Sospechosa->value]);
        }
    }

    /**
     * Check if a user has access to a project (student or director).
     */
    private function tieneAccesoAProyecto($user, int $proyectoId): bool
    {
        $proyecto = Proyecto::find($proyectoId);

        if (! $proyecto) {
            return false;
        }

        if ($proyecto->director_id === $user->id) {
            return true;
        }

        return $proyecto->estudiantes()
            ->where('user_id', $user->id)
            ->exists();
    }
}
