<?php

declare(strict_types=1);

namespace App\Services\Directors;

use App\Enums\UserRole;
use App\Exceptions\DirectorAssignmentException;
use App\Models\User;

/**
 * Blocks delete / role-change of a Director who still owns projects.
 */
final class DirectorAssignmentGuard
{
    public function assignedProjectCount(User $user): int
    {
        return $user->proyectosDirigidos()->count();
    }

    public function otherDirectorsExist(int $excludeUserId): bool
    {
        return User::query()
            ->where('role', UserRole::Director)
            ->where('id', '!=', $excludeUserId)
            ->exists();
    }

    public function assertCanDelete(User $user): void
    {
        if ($user->role !== UserRole::Director) {
            return;
        }

        $count = $this->assignedProjectCount($user);

        if ($count === 0) {
            return;
        }

        throw DirectorAssignmentException::hasProjects(
            "No se puede eliminar al director porque tiene {$count} proyecto(s) asignado(s). Reasigne los proyectos o use la opción de distribución aleatoria.",
            $count,
            $this->otherDirectorsExist($user->id),
        );
    }

    public function assertCanChangeRole(User $user, UserRole $newRole): void
    {
        if ($user->role !== UserRole::Director || $newRole === UserRole::Director) {
            return;
        }

        $count = $this->assignedProjectCount($user);

        if ($count === 0) {
            return;
        }

        throw DirectorAssignmentException::hasProjects(
            "No se puede cambiar el rol del director porque tiene {$count} proyecto(s) asignado(s). Reasigne o redistribuya los proyectos primero.",
            $count,
            false,
        );
    }
}
