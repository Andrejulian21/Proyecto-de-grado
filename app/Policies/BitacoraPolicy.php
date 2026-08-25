<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\User;

/**
 * Bitácora authorization (issue #38): default-deny.
 *
 * Central rule adapted to bitácoras — Coordinador, director of the project
 * or student of the project. External evaluators never get bitácora
 * access. Anything not enumerated is denied (`false` → 403).
 */
class BitacoraPolicy
{
    /**
     * View a bitácora (or the bitácoras of a project, for listings).
     */
    public function view(User $actor, Bitacora|Proyecto $resource): bool
    {
        $proyecto = $resource instanceof Proyecto ? $resource : $resource->proyecto;

        return $proyecto !== null && $this->tieneAccesoAProyecto($actor, $proyecto);
    }

    /**
     * Create a bitácora inside a project.
     */
    public function create(User $actor, Proyecto $proyecto): bool
    {
        return $this->tieneAccesoAProyecto($actor, $proyecto);
    }

    /**
     * Update / re-request a bitácora code.
     */
    public function update(User $actor, Bitacora $bitacora): bool
    {
        return $bitacora->proyecto !== null && $this->tieneAccesoAProyecto($actor, $bitacora->proyecto);
    }

    /**
     * Delete a bitácora: coordinator or the project's director.
     * No delete endpoint exists today; the rule is expressed for
     * completeness (default deny).
     */
    public function delete(User $actor, Bitacora $bitacora): bool
    {
        if ($actor->role === UserRole::Coordinador) {
            return true;
        }

        return $bitacora->proyecto !== null
            && (int) $bitacora->proyecto->director_id === $actor->id;
    }

    /**
     * Sign a bitácora with the TOTP-style code: director of the project
     * only. Pending derived issue #45 (audit event + flow hardening).
     */
    public function sign(User $actor, Bitacora $bitacora): bool
    {
        return $bitacora->proyecto !== null
            && (int) $bitacora->proyecto->director_id === $actor->id;
    }

    private function tieneAccesoAProyecto(User $actor, Proyecto $proyecto): bool
    {
        if ($actor->role === UserRole::Coordinador) {
            return true;
        }

        if ((int) $proyecto->director_id === $actor->id) {
            return true;
        }

        return $proyecto->estudiantes()
            ->where('user_id', $actor->id)
            ->exists();
    }
}
