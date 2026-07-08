<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * User model policy (T-019).
 *
 * All user-management actions are coordinator-only:
 *   - viewAny / create / update / delete
 *
 * EvaluadorExterno can never manage other users (the coordinator
 * does that on their behalf). Estudiante and Director have read-only
 * access to the user list via the view-admin gate, but cannot mutate
 * it.
 */
class UserPolicy
{
    /**
     * True when the actor is a Coordinador.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * Coordinators create external evaluators and seed new
     * whitelist rows; nobody else can.
     */
    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * Coordinators update user roles and whitelist entries.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Coordinador;
    }

    /**
     * Coordinators remove users from the system. Used by the
     * whitelist DELETE endpoint.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Coordinador;
    }
}
