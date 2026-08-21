<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConsultaNotasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultaNotasController extends Controller
{
    public function __construct(
        private readonly ConsultaNotasService $consultaNotas,
    ) {}

    /**
     * GET /api/notas
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semestre_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'proyecto_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entrega_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'estado_nota' => ['sometimes', 'nullable', 'in:calificada,sin_calificar'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        try {
            $data = $this->consultaNotas->listar($request->user(), $validated);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'error' => $exception->getMessage() ?: 'No autorizado.',
            ], 403);
        }

        return response()->json(['data' => $data]);
    }
}
