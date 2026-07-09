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
        $notificaciones = Notificacion::where('user_id', $request->user()->id)
            ->orderByDesc('sent_at')
            ->get();

        return response()->json(['data' => $notificaciones]);
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
