<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semestre;
use App\Models\SeguimientoObservacion;
use App\Services\SeguimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeguimientoController extends Controller
{
    public function __construct(
        private readonly SeguimientoService $seguimientoService,
    ) {}

    /**
     * Get full seguimiento data for all projects in a semester.
     */
    public function porSemestre(Semestre $semestre): JsonResponse
    {
        $data = $this->seguimientoService->obtenerSeguimiento($semestre->id);

        return response()->json(['data' => $data]);
    }

    /**
     * Upsert a seguimiento observation for a (project, semester, phase) tuple.
     *
     * Expects JSON body:
     *   { proyecto_id: int, semestre_id: int, fase: string, observacion: string|null }
     */
    public function guardarObservacion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|integer|exists:proyectos,id',
            'semestre_id' => 'required|integer|exists:semestres,id',
            'fase' => 'required|string|max:50',
            'observacion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $observacion = SeguimientoObservacion::updateOrCreate(
            [
                'proyecto_id' => $request->integer('proyecto_id'),
                'semestre_id' => $request->integer('semestre_id'),
                'fase' => $request->input('fase'),
            ],
            [
                'observacion' => $request->input('observacion'),
            ]
        );

        return response()->json(['data' => $observacion], 200);
    }
}
