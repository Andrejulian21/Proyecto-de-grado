<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoInvitacionEvaluador;
use App\Http\Controllers\Controller;
use App\Models\EvaluadorProyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluadorProyectoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asignaciones = EvaluadorProyecto::where('proyecto_id', $request->integer('proyecto_id'))
            ->with('evaluador:id,name,email')
            ->get();

        return response()->json(['data' => $asignaciones]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
            'evaluador_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $asignacion = EvaluadorProyecto::create([
            'proyecto_id' => $data['proyecto_id'],
            'evaluador_id' => $data['evaluador_id'],
            'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
            'assigned_at' => now(),
        ]);

        $asignacion->load('evaluador:id,name,email');

        return response()->json(['data' => $asignacion], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
            'evaluador_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deleted = EvaluadorProyecto::where('proyecto_id', $request->integer('proyecto_id'))
            ->where('evaluador_id', $request->integer('evaluador_id'))
            ->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Asignación no encontrada.'], 404);
        }

        return response()->json(['message' => 'Evaluador desasignado correctamente.']);
    }
}
