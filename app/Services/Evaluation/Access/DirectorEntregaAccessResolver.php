<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Access;

use App\Contracts\Evaluation\EvaluationAccessResolver;
use App\Exceptions\DocumentEvaluationException;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\User;

/**
 * Director may evaluate entregas linked to projects they direct.
 */
final class DirectorEntregaAccessResolver implements EvaluationAccessResolver
{
    public function resolve(User $user, int $entregaId): array
    {
        $entrega = Entrega::query()
            ->with(['proyectos'])
            ->whereKey($entregaId)
            ->first();

        if (! $entrega) {
            throw DocumentEvaluationException::notFound();
        }

        $proyecto = $this->resolveDirectedProject($entrega, $user->id);

        if (! $proyecto) {
            throw DocumentEvaluationException::forbidden(
                'No tienes permiso para evaluar esta entrega como Director.',
            );
        }

        return ['proyecto' => $proyecto, 'entrega' => $entrega];
    }

    private function resolveDirectedProject(Entrega $entrega, int $userId): ?Proyecto
    {
        foreach ($entrega->proyectos as $proyecto) {
            if ((int) $proyecto->director_id === $userId) {
                return $proyecto;
            }
        }

        return null;
    }
}
