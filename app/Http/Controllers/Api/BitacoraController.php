<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EstadoFirma;
use App\Http\Controllers\Controller;
use App\Models\Bitacora;
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

            return response()->json(['data' => $bitacora->fresh()]);
        }

        return response()->json(['error' => 'Estado de firma no válido para esta acción.'], 422);
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
