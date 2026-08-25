<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EvaluadorProyecto;
use App\Models\User;

/**
 * Entrega authorization (issue #38): default-deny.
 *
 * Central rule — a user may access an entrega (or a project-bound resource)
 * when they are a Coordinador, the director of a linked project, a student
 * of a linked project, or an evaluator assigned to a linked project.
 * Anything not enumerated is denied (`false` → 403).
 *
 * Membership is resolved through the Entrega model's existing helpers
 * (`esDirector`, `esEstudiante`), which check pivot projects first and the
 * direct `proyecto_id` FK as fallback.
 */
class EntregaPolicy
{
    /**
     * View a single entrega (detail + version history).
     */
    public function view(User $actor, Entrega $entrega): bool
    {
        if ($actor->role === UserRole::Coordinador) {
            return true;
        }

        if ($actor->role === UserRole::EvaluadorExterno) {
            return $this->esEvaluadorAsignado($actor, $entrega);
        }

        // Director or Estudiante: membership in any linked project.
        return $entrega->esDirector($actor->id) || $entrega->esEstudiante($actor->id);
    }

    /**
     * Create an entrega: coordinator only.
     */
    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * Update an entrega's configuration: coordinator only.
     */
    public function update(User $actor): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * Delete an entrega: coordinator only.
     */
    public function delete(User $actor): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * "Finales" document bank: coordinator only.
     */
    public function manage(User $actor): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * Director approves/rejects an entrega: director of a linked project.
     */
    public function review(User $actor, Entrega $entrega): bool
    {
        return $entrega->esDirector($actor->id);
    }

    /**
     * Director enables a solicited entrega: director of a linked project.
     */
    public function habilitar(User $actor, Entrega $entrega): bool
    {
        return $entrega->esDirector($actor->id);
    }

    /**
     * Student requests habilitación: student of a linked project.
     */
    public function solicitar(User $actor, Entrega $entrega): bool
    {
        return $entrega->esEstudiante($actor->id);
    }

    /**
     * Delete a document version: student of a linked project.
     *
     * Pending derived issue #46: the version belongs to one specific
     * project pivot (`entrega_proyecto_id`); the pivot-level ownership
     * filter is implemented in that issue. Here the policy expresses the
     * rule (only the student who owns the delivery may delete).
     */
    public function deleteVersion(User $actor, Entrega $entrega): bool
    {
        return $entrega->esEstudiante($actor->id);
    }

    private function esEvaluadorAsignado(User $actor, Entrega $entrega): bool
    {
        $proyectoIds = $entrega->proyectos()->pluck('proyectos.id')->all();

        if ($entrega->proyecto_id !== null) {
            $proyectoIds[] = $entrega->proyecto_id;
        }

        $proyectoIds = array_values(array_unique($proyectoIds));

        if ($proyectoIds === []) {
            return false;
        }

        return EvaluadorProyecto::query()
            ->where('evaluador_id', $actor->id)
            ->whereIn('proyecto_id', $proyectoIds)
            ->exists();
    }
}
