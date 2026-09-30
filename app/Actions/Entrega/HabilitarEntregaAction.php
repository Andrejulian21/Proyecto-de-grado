<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Enums\EstadoEntrega;
use App\Models\AuditLog;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single-purpose use case: a director enables a solicited entrega so the
 * student can upload versions. The director membership check stays in the
 * controller (authorization layer).
 *
 * Re-enabling ONE project's delivery must not touch the other projects of the
 * same semester-wide template: clearing every graded pivot erased grades that
 * belonged to projects this director never reviewed. Callers therefore pass
 * the resolved EntregaProyecto to scope both the guard and the unfreeze;
 * callers that pass null keep the semester-wide behaviour.
 */
final class HabilitarEntregaAction
{
    public function handle(
        Entrega $entrega,
        int $userId,
        string $ip,
        ?string $userAgent,
        ?EntregaProyecto $entregaProyecto = null,
    ): Entrega {
        // RF-FREEZE-01: habilitar runs from solicitada (enable uploads) or
        // from a frozen graded delivery (unfreeze: clear grade, reopen).
        $isSolicitada = $entrega->status->value === EstadoEntrega::Solicitada->value;
        $hasGradedPivot = $this->pivotesCalificados($entrega, $entregaProyecto)->exists();

        if (! $isSolicitada && ! $hasGradedPivot) {
            throw new EntregaActionException('La entrega no está en estado solicitada.');
        }

        $entrega->update(['status' => EstadoEntrega::Pendiente->value]);

        // RF-FREEZE-01: habilitar unfreezes graded pivots so the student can
        // upload again. Scoped to one project when the caller resolved it.
        $this->pivotesCalificados($entrega, $entregaProyecto)
            ->update(['director_grade' => null, 'estado' => null]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'entrega.habilitar',
            'description' => "Director habilitó entrega #{$entrega->id}",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'metadata' => [
                'entrega_id' => $entrega->id,
                'entrega_proyecto_id' => $entregaProyecto?->id,
                'proyecto_ids' => $entregaProyecto !== null
                    ? [$entregaProyecto->proyecto_id]
                    : $entrega->proyectos()->pluck('proyectos.id')->all(),
            ],
        ]);

        $entrega->load('proyectos:id,code,title');

        return $entrega;
    }

    /**
     * The graded pivots this habilitación is allowed to unfreeze.
     *
     * `estado` is cleared together with the grade: the per-project verdict is
     * what makes a delivery terminal for the review action, so leaving it in
     * place would re-freeze the project the director just re-enabled.
     *
     * @return Builder<EntregaProyecto>
     */
    private function pivotesCalificados(Entrega $entrega, ?EntregaProyecto $entregaProyecto): Builder
    {
        $query = EntregaProyecto::query()
            ->where('entrega_id', $entrega->id)
            ->whereNotNull('director_grade');

        if ($entregaProyecto !== null) {
            $query->whereKey($entregaProyecto->id);
        }

        return $query;
    }
}
