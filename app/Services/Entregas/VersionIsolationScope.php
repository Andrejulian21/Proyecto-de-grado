<?php

declare(strict_types=1);

namespace App\Services\Entregas;

use App\Enums\UserRole;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
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
     * Resolve the per-project delivery (EntregaProyecto) of ONE project inside
     * a shared entrega, or null when that project is not linked to it.
     *
     * An `entrega` is a semester-wide template, so its id alone never says
     * WHICH project delivery is being supervised. Every project-scoped
     * endpoint therefore resolves `proyecto requested -> pivot` through this
     * single place instead of re-deriving the join per route. A null result
     * is the "no such project delivery" signal the controllers render as 404.
     */
    public function resolverPivote(Entrega $entrega, int $proyectoId): ?EntregaProyecto
    {
        return EntregaProyecto::query()
            ->where('entrega_id', $entrega->id)
            ->where('proyecto_id', $proyectoId)
            ->first();
    }

    /**
     * Whether the actor is related to a specific project, ignoring whether
     * that project happens to be linked to any entrega.
     *
     * `EntregaPolicy::view` only proves the actor supervises (or teaches) SOME
     * project of the entrega. Reusing it as-is would let a director who
     * supervises project A read project B just by naming B in the query
     * string, so project-scoped resolution needs this narrower rule.
     *
     * Same role matrix as EntregaPolicy, evaluated against ONE project. Kept
     * free of any entrega lookup on purpose: the caller can therefore reject
     * an unrelated project (403) BEFORE checking whether it belongs to the
     * entrega (404), so a 403 never doubles as an oracle telling whether a
     * given project id is linked to that entrega.
     */
    public function relacionadoConProyecto(User $actor, int $proyectoId): bool
    {
        $proyecto = Proyecto::find($proyectoId);

        if ($proyecto === null) {
            return false;
        }

        return match ($actor->role) {
            UserRole::Coordinador => true,
            UserRole::Director => (int) $proyecto->director_id === (int) $actor->id,
            UserRole::Estudiante => $proyecto->estudiantes()->where('user_id', $actor->id)->exists(),
            UserRole::EvaluadorExterno => EvaluadorProyecto::query()
                ->where('evaluador_id', $actor->id)
                ->where('proyecto_id', $proyectoId)
                ->exists(),
            default => false,
        };
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
