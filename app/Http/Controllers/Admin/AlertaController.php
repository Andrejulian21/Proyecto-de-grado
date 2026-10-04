<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Services\Alertas\AlertaGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coordinator-only alert panel.
 *
 * Both actions are registered inside the `role:Coordinador` route group, so
 * authorization reuses the existing RoleMiddleware instead of a new mechanism.
 */
class AlertaController extends Controller
{
    public function __construct(
        private readonly AlertaGenerator $generador,
    ) {}

    /**
     * List alerts, reconciling the current state first.
     *
     * Regenerating before responding is what makes this endpoint the single
     * source of truth: the browser used to derive alerts from two generic
     * endpoints on every mount, which meant the panel and the KPI could
     * disagree on the same screen.
     *
     * `?revisadas=0` (default) → pending only, `?revisadas=1` → reviewed only,
     * `?revisadas=todas` → both.
     */
    public function index(Request $request): JsonResponse
    {
        $this->generador->generar();

        $filtro = (string) $request->query('revisadas', '0');

        $alertas = Alerta::query()
            ->when($filtro === '0', fn ($query) => $query->noRevisadas())
            ->when($filtro === '1', fn ($query) => $query->revisadas())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $alertas]);
    }

    /**
     * Mark an alert as reviewed. Idempotent: reviewing twice keeps the first
     * timestamp and does not duplicate the audit trail.
     */
    public function revisar(Request $request, Alerta $alerta): JsonResponse
    {
        if (! $alerta->estaRevisada()) {
            $alerta->update([
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()?->id,
            ]);

            AuditEvent::dispatch(
                $request->user(),
                'alerta.revisada',
                "Alerta #{$alerta->id} ({$alerta->clave}) marcada como revisada.",
                [
                    'alerta_id' => $alerta->id,
                    'clave' => $alerta->clave,
                    'tipo' => $alerta->tipo->value,
                    'proyecto_id' => $alerta->proyecto_id,
                ],
            );
        }

        return response()->json(['data' => $alerta->fresh()]);
    }
}
