<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Enums\EstadoEntrega;
use App\Models\AuditLog;
use App\Models\Entrega;
use Carbon\Carbon;

/**
 * Single-purpose use case: a student requests habilitación to upload
 * versions. Guards run in the same order as the original controller flow
 * (time window, membership, creation status).
 */
final class SolicitarEntregaAction
{
    public function handle(Entrega $entrega, int $userId, string $ip, ?string $userAgent): Entrega
    {
        // Verify submission is within the allowed time window
        $now = now();
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Check start date constraint
        if ($entrega->start_date) {
            $startDate = $entrega->start_date instanceof Carbon
                ? $entrega->start_date->format('Y-m-d')
                : $entrega->start_date;

            if ($today < $startDate) {
                throw new EntregaActionException(
                    'La entrega aún no está disponible. La fecha de inicio es '.$startDate.'.'
                );
            }

            if ($today === $startDate && $entrega->start_time && $currentTime < $entrega->start_time) {
                throw new EntregaActionException(
                    'La entrega aún no está disponible. La hora de inicio es las '.$entrega->start_time.'.'
                );
            }
        }

        // Check due date constraint
        $dueDate = $entrega->due_date instanceof Carbon
            ? $entrega->due_date->format('Y-m-d')
            : $entrega->due_date;

        if ($today > $dueDate) {
            throw new EntregaActionException(
                'La fecha límite de la entrega ya pasó ('.$dueDate.').'
            );
        }

        if ($today === $dueDate && $entrega->hora_maxima && $currentTime > $entrega->hora_maxima) {
            throw new EntregaActionException(
                'La hora máxima para esta entrega era las '.$entrega->hora_maxima.'.'
            );
        }

        if (! $entrega->esEstudiante($userId)) {
            throw new EntregaActionException('No eres estudiante de este proyecto.', 403);
        }

        if ($entrega->status->value !== EstadoEntrega::Creada->value) {
            throw new EntregaActionException('La entrega no está en estado de creación.');
        }

        $entrega->update(['status' => EstadoEntrega::Solicitada->value]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'entrega.solicitar',
            'description' => "Estudiante solicitó habilitación para entrega #{$entrega->id}",
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
