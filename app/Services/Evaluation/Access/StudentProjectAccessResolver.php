<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Access;

use App\Contracts\Evaluation\EvaluationAccessResolver;
use App\Exceptions\DocumentEvaluationException;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\User;

/**
 * Student may evaluate entregas belonging to their assigned project.
 */
final class StudentProjectAccessResolver implements EvaluationAccessResolver
{
    public function resolve(User $user, int $entregaId): array
    {
        $proyecto = Proyecto::query()
            ->whereHas('estudiantes', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->first();

        if (! $proyecto) {
            throw DocumentEvaluationException::notFound('No tienes un proyecto asignado.');
        }

        $entrega = Entrega::paraProyecto($proyecto->id)->where('entregas.id', $entregaId)->first();

        if (! $entrega) {
            throw DocumentEvaluationException::forbidden();
        }

        return ['proyecto' => $proyecto, 'entrega' => $entrega];
    }
}
