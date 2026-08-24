<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\Semestre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SemestreController extends Controller
{
    public function index(): JsonResponse
    {
        $semestres = Semestre::query()->orderByDesc('start_date')->get();

        return response()->json(['data' => $semestres]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:semestres,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (($data['is_active'] ?? true) && Semestre::where('is_active', true)->count() >= 2) {
            return response()->json([
                'errors' => ['is_active' => ['Ya existen 2 semestres activos. Desactive uno antes de activar otro.']],
            ], 422);
        }

        $semestre = Semestre::create($data);

        return response()->json(['data' => $semestre], 201);
    }

    public function update(Request $request, Semestre $semestre): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:semestres,name,' . $semestre->id,
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $activating = $data['is_active'] ?? null;
        if ($activating === true && ! $semestre->is_active) {
            $currentActive = Semestre::where('is_active', true)->count();
            $alreadyActiveOthers = $semestre->is_active ? $currentActive - 1 : $currentActive;
            if ($alreadyActiveOthers >= 2) {
                return response()->json([
                    'errors' => ['is_active' => ['Ya existen 2 semestres activos. Desactive uno antes de activar otro.']],
                ], 422);
            }
        }

        $semestre->update($data);

        return response()->json(['data' => $semestre->fresh()]);
    }

    public function destroy(Semestre $semestre): JsonResponse
    {
        // Regla de negocio: solo se puede eliminar un grupo (semestre) que no
        // tenga nada ligado. Se verifican las tablas que referencian semester_id.
        if ($semestre->proyectos()->exists()) {
            return response()->json([
                'error' => 'No se puede eliminar el grupo porque tiene proyectos vinculados.',
            ], 422);
        }

        if (Entrega::where('semester_id', $semestre->id)->exists()) {
            return response()->json([
                'error' => 'No se puede eliminar el grupo porque tiene entregas vinculadas.',
            ], 422);
        }

        $semestre->delete();

        return response()->json(['message' => 'Semestre eliminado']);
    }
}
