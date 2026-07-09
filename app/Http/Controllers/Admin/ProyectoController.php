<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProyectoController extends Controller
{
    public function index(): JsonResponse
    {
        $proyectos = Proyecto::query()
            ->with(['semestre', 'director', 'estudiantes'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $proyectos]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'semester_id' => 'required|exists:semestres,id',
            'director_id' => 'nullable|exists:users,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $studentIds = $data['student_ids'] ?? [];

        if (count($studentIds) > 3) {
            return response()->json([
                'errors' => ['student_ids' => ['Máximo 3 estudiantes por proyecto.']],
            ], 422);
        }

        if (count($studentIds) >= 3) {
            $data['requires_group_justification'] = true;
        }

        $proyecto = Proyecto::create([
            'title' => $data['title'],
            'semester_id' => $data['semester_id'],
            'director_id' => $data['director_id'] ?? null,
            'requires_group_justification' => $data['requires_group_justification'] ?? false,
        ]);

        if (! empty($studentIds)) {
            $proyecto->estudiantes()->attach($studentIds);
        }

        $proyecto->load(['semestre', 'director', 'estudiantes']);

        return response()->json(['data' => $proyecto], 201);
    }
}
