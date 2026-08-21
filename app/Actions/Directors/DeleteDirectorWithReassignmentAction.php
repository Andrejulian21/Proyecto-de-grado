<?php

declare(strict_types=1);

namespace App\Actions\Directors;

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Exceptions\DirectorAssignmentException;
use App\Models\AuthorizedEmail;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Atomically reassign a director's projects to other directors, then delete
 * the user. Never deletes first: a failure after reassignment rolls back.
 *
 * @return array{message: string, reasignaciones: list<array{proyecto_id: int, nuevo_director_id: int}>}
 */
final class DeleteDirectorWithReassignmentAction
{
    /**
     * @return array{message: string, reasignaciones: list<array{proyecto_id: int, nuevo_director_id: int}>}
     */
    public function handle(User $director, User $actor): array
    {
        if ($director->role !== UserRole::Director) {
            throw DirectorAssignmentException::notADirector(
                'Solo se puede usar la reasignación con usuarios que tienen rol de director.',
            );
        }

        return DB::transaction(function () use ($director, $actor): array {
            $locked = User::query()->whereKey($director->id)->lockForUpdate()->firstOrFail();

            $proyectos = Proyecto::query()
                ->where('director_id', $locked->id)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $recipients = User::query()
                ->where('role', UserRole::Director)
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->get();

            if ($proyectos->isNotEmpty() && $recipients->isEmpty()) {
                throw DirectorAssignmentException::noDirectorsAvailable(
                    'No hay otros directores disponibles para reasignar los proyectos. Agregue otro director antes de eliminar a este usuario.',
                );
            }

            $reasignaciones = [];

            if ($proyectos->isNotEmpty()) {
                $shuffled = $recipients->shuffle()->values();
                $recipientCount = $shuffled->count();

                foreach ($proyectos as $index => $proyecto) {
                    $nuevo = $shuffled[$index % $recipientCount];
                    $proyecto->director_id = $nuevo->id;
                    $proyecto->save();
                    $reasignaciones[] = [
                        'proyecto_id' => $proyecto->id,
                        'nuevo_director_id' => $nuevo->id,
                    ];
                }
            }

            $email = $locked->email;
            $id = $locked->id;
            $count = $proyectos->count();

            AuthorizedEmail::query()->where('email', $email)->delete();
            $locked->delete();

            if ($count > 0) {
                AuditEvent::dispatch(
                    $actor,
                    'director.projects_reassigned',
                    "Reassigned {$count} project(s) from {$email}",
                    [
                        'deleted_user_id' => $id,
                        'reasignaciones' => $reasignaciones,
                    ],
                );
            }

            AuditEvent::dispatch(
                $actor,
                'user.deleted',
                "Deleted user {$email}",
                ['deleted_user_id' => $id],
            );

            $message = $count > 0
                ? "Director eliminado. Se reasignaron {$count} proyecto(s) entre los directores existentes."
                : 'Director eliminado.';

            return [
                'message' => $message,
                'reasignaciones' => $reasignaciones,
            ];
        });
    }
}
