<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Issue #53: la tabla crece una fila por evento y por usuario —
        // paginar en el servidor. Sin consumidor frontend actual; el
        // contrato pasa de array plano a paginador (data + total + per_page).
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $notificaciones = Notificacion::where('user_id', $request->user()->id)
            ->orderByDesc('sent_at')
            ->paginate($perPage);

        return response()->json($notificaciones);
    }

    public function noLeidas(Request $request): JsonResponse
    {
        $count = Notificacion::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    public function marcarLeida(Request $request, Notificacion $notificacion): JsonResponse
    {
        if ($notificacion->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $notificacion->update(['is_read' => true]);

        return response()->json(['data' => $notificacion->fresh()]);
    }
}
