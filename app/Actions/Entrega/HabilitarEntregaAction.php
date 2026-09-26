<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Enums\EstadoEntrega;
use App\Models\AuditLog;
use App\Models\Entrega;
use App\Models\EntregaProyecto;

/**
 * Single-purpose use case: a director enables a solicited entrega so the
 * student can upload versions. The director membership check stays in the
 * controller (authorization layer).
 */
final class HabilitarEntregaAction
{
    public function handle(Entrega $entrega, int $userId, string $ip, ?string $userAgent): Entrega
    {
        // RF-FREEZE-01: habilitar runs from solicitada (enable uploads) or
        // from a frozen graded delivery (unfreeze: clear grade, reopen).
        $isSolicitada = $entrega->status->value === EstadoEntrega::Solicitada->value;
        $hasGradedPivot = EntregaProyecto::where('entrega_id', $entrega->id)
            ->whereNotNull('director_grade')
            ->exists();

        if (! $isSolicitada && ! $hasGradedPivot) {
            throw new EntregaActionException('La entrega no está en estado solicitada.');
        }

        $entrega->update(['status' => EstadoEntrega::Pendiente->value]);

        // RF-FREEZE-01: habilitar unfreezes graded pivots so the student can
        // upload again.
        EntregaProyecto::where('entrega_id', $entrega->id)
            ->whereNotNull('director_grade')
            ->update(['director_grade' => null]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'entrega.habilitar',
            'description' => "Director habilitó entrega #{$entrega->id}",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'metadata' => [
                'entrega_id' => $entrega->id,
                'proyecto_ids' => $entrega->proyectos()->pluck('proyectos.id')->all(),
            ],
        ]);

        $entrega->load('proyectos:id,code,title');

        return $entrega;
    }
}
