<?php

declare(strict_types=1);

namespace App\Services\Entregas;

use App\Enums\UserRole;
use App\Models\AiDocumentEvaluation;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Per-project isolation of the data a project submits to a shared entrega.
 *
 * The `entregas` row is a semester-wide template: title, phase, description,
 * due date, `archivos_requeridos` and `grade_percentage` are visible to every
 * project linked to it. Everything a project *submits* — uploaded versions,
 * `file_path`, `original_name`, `uploaded_at`, `director_notes`,
 * `director_grade` and the AI analyses — hangs off the `entrega_proyecto`
 * pivot and is private to the owning project.
 *
 * Only a student is narrowed down. A director, a coordinator and an external
 * evaluator supervise or grade those entregas and legitimately need every
 * version, so filtering them would break supervision instead of protecting
 * anything. Keeping the rule here — a single place, reused by every route —
 * is what stops a new endpoint from silently reintroducing the leak.
 */
final class VersionIsolationScope
{
    /**
     * Narrow a version query to what the actor is allowed to see.
     *
     * Non-student roles pass through untouched. A student is restricted to
     * the versions attached to the pivots of their own projects; a student
     * with no project gets an empty result (default-deny), which also keeps
     * an eager load from silently degrading back to the full relation.
     *
     * Accepts either a plain query builder or the `Relation` handed to an
     * eager-load constraint closure; in the latter case the constraints are
     * applied to the relation's own query, which is what Laravel executes.
     *
     * @param  Builder<VersionDocumento>|Relation<VersionDocumento, *>  $query
     * @return Builder<VersionDocumento>
     */
    public function apply(Builder|Relation $query, User $actor): Builder
    {
        if (! $this->esEstudiante($actor)) {
            return $this->queryOf($query);
        }

        return $this->queryOf($query)->paraProyecto($this->proyectosDeEstudiante($actor));
    }

    /**
     * Unwrap an eager-load relation into the query builder it executes.
     *
     * @param  Builder<VersionDocumento>|Relation<VersionDocumento, *>  $query
     * @return Builder<VersionDocumento>
     */
    private function queryOf(Builder|Relation $query): Builder
    {
        return $query instanceof Relation ? $query->getQuery() : $query;
    }

    /**
     * Narrow an AI-analysis query to what the actor is allowed to see.
     *
     * An analysis belongs to a project in two ways: either it hangs off an
     * official version (so the version's pivot decides) or it was produced
     * from a temporary pre-submission upload, which is never persisted as a
     * version and can only be attributed to its author.
     *
     * @param  Builder<AiDocumentEvaluation>  $query
     * @return Builder<AiDocumentEvaluation>
     */
    public function applyToAnalyses(Builder $query, User $actor): Builder
    {
        if (! $this->esEstudiante($actor)) {
            return $query;
        }

        $proyectoIds = $this->proyectosDeEstudiante($actor);

        return $query->where(function (Builder $group) use ($proyectoIds, $actor) {
            if ($proyectoIds !== []) {
                $group->orWhereHas(
                    'versionDocumento',
                    fn (Builder $versions) => $versions->paraProyecto($proyectoIds),
                );
            }

            $group->orWhere(function (Builder $temporal) use ($actor) {
                $temporal->whereNull('version_documento_id')
                    ->where('user_id', $actor->id);
            });
        });
    }

    /**
     * Ids of the projects the student belongs to (`proyecto_estudiante`).
     *
     * Same membership rule as StudentProjectAccessResolver and the per-pivot
     * freeze already enforced on upload.
     *
     * @return list<int>
     */
    public function proyectosDeEstudiante(User $actor): array
    {
        return $actor->proyectosComoEstudiante()
            ->pluck('proyectos.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function esEstudiante(User $actor): bool
    {
        return $actor->role === UserRole::Estudiante;
    }
}
