<?php

declare(strict_types=1);

namespace App\Contracts\Evaluation;

use App\Exceptions\DocumentEvaluationException;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\User;

/**
 * Resolves which project/entrega a user may evaluate.
 * Keeps DocumentEvaluationService free of role-specific queries.
 */
interface EvaluationAccessResolver
{
    /**
     * @return array{proyecto: Proyecto, entrega: Entrega}
     *
     * @throws DocumentEvaluationException
     */
    public function resolve(User $user, int $entregaId): array;
}
