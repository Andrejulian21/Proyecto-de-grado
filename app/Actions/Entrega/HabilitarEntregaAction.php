<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Enums\EstadoEntrega;
use App\Models\AuditLog;
use App\Models\Entrega;

/**
 * Single-purpose use case: a director enables a solicited entrega so the
 * student can upload versions. The director membership check stays in the
 * controller (authorization layer).
 */
final class HabilitarEntregaAction
{
    public function handle(Entrega $entrega, int $userId, string $ip, ?string $userAgent): Entrega
    {
        if ($entrega->status->value !== EstadoEntrega::Solicitada->value) {
            throw new EntregaActionException('La entrega no está en estado solicitada.');
        }

        $entrega->update(['status' => EstadoEntrega::Pendiente->value]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'entrega.habilitar',
            'description' => "Director habilitó entrega #{$entrega->id}",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'metadata' => [
                'entrega_id' => $entrega->id,
                'proyecto_id' => $entrega->proyecto_id,
            ],
        ]);

        $entrega->load('proyecto:id,code,title');

        return $entrega;
    }
}
