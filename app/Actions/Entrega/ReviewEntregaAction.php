<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Notificacion;
use App\Models\Proyecto;
use App\Models\VersionDocumento;
use App\Services\Entregas\NotaEntregaResolver;
use Illuminate\Support\Facades\DB;

/**
 * Single-purpose use case: the director approves/rejects an entrega with a
 * grade and feedback, auto-advances the phase of the REVIEWED project and
 * notifies only the students of that project (issue #49).
 *
 * An entrega is a semester-wide TEMPLATE, so a verdict about one project's
 * delivery must not be written onto it: that would mark the whole semester as
 * approved/rejected and, through the terminal-status guard, block the review
 * of every other project of that entrega. Callers that review ONE project
 * therefore pass the resolved EntregaProyecto, and the verdict lands on the
 * pivot (`estado`, `director_grade`, `observaciones_director`) — the columns
 * that already exist per project. Legacy callers that pass null keep the
 * original semester-wide behaviour.
 */
final class ReviewEntregaAction
{
    public function __construct(
        private readonly NotaEntregaResolver $notas,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated review payload
     * @param  EntregaProyecto|null  $entregaProyecto  project-scoped delivery; null = legacy global review
     */
    public function handle(Entrega $entrega, array $data, int $senderId, ?EntregaProyecto $entregaProyecto = null): Entrega
    {
        $porProyecto = $entregaProyecto !== null;

        // RF-NOT-03: the director may only review/edit an entrega that is
        // still open (non-terminal status and due date not passed).
        if (! $this->esEditable($entrega, $entregaProyecto)) {
            throw new EntregaActionException('La entrega está cerrada; la nota y las observaciones no pueden modificarse', 422);
        }

        // The review writes to five tables (entregas, versiones_documento,
        // entrega_proyecto, proyectos, notificaciones). A mid-process failure
        // must not leave the delivery marked approved without its phase
        // advance or notifications — the whole operation is atomic (#49).
        return DB::transaction(function () use ($entrega, $data, $senderId, $entregaProyecto, $porProyecto): Entrega {
            // Shared template columns are ONLY written by the legacy global
            // review. In a project-scoped review they would be a lie: the
            // other projects of the semester have not been reviewed yet.
            if (! $porProyecto) {
                $entrega->update([
                    'status' => $data['status'],
                    'consolidated_grade' => $data['consolidated_grade'] ?? null,
                    'evaluation_complete' => true,
                ]);
            }

            // Resolve the reviewed version (RF-NOT-02: notes are per version).
            // Scoped to the requested pivot so a version of another project
            // cannot be graded through this project's filter (404 when the
            // version does not belong to the requested delivery).
            $versionQuery = VersionDocumento::where('entrega_id', $entrega->id)
                ->where('id', $data['version_id']);

            if ($porProyecto) {
                $versionQuery->where('entrega_proyecto_id', $entregaProyecto->id);
            }

            $version = $versionQuery->firstOrFail();

            // Observations belong to the selected version of any requested document.
            if (array_key_exists('director_notes', $data) && $data['director_notes'] !== null && $data['director_notes'] !== '') {
                $version->update(['director_notes' => $data['director_notes']]);
            }

            // D3-rev: the director grade and observations belong to the STUDENT
            // delivery (per project). The reviewed version resolves its
            // EntregaProyecto; legacy versions without a pivot are skipped.
            $pivot = $entregaProyecto ?? $version->entregaProyecto;

            if ($pivot !== null) {
                $pivotData = [];

                // Per-project verdict. A project-scoped review never touches
                // the semester template status, so `estado` on the pivot is
                // the only place this decision can live.
                if ($porProyecto) {
                    $pivotData['estado'] = $data['status'];
                }

                // RF-NOT-02: the grade is only captured when the delivery is
                // approved; it is never persisted on a non-approval review.
                if ($data['status'] === 'aprobada' && isset($data['director_grade'])) {
                    $pivotData['director_grade'] = $data['director_grade'];
                }

                // RF-FREEZE-01: rechazada reopens the pivot — the grade stays
                // null so the student can upload corrections.
                if ($data['status'] === 'rechazada') {
                    $pivotData['director_grade'] = null;
                }

                if (array_key_exists('director_notes', $data) && $data['director_notes'] !== null && $data['director_notes'] !== '') {
                    $pivotData['observaciones_director'] = $data['director_notes'];
                }

                if ($pivotData !== []) {
                    $pivot->update($pivotData);
                }
            }

            // The reviewed project is the one owning the reviewed version's
            // per-project delivery (EntregaProyecto). Legacy versions without
            // a pivot fall back to the entrega's first linked project. The
            // semester-wide collection is NEVER loaded (issue #49): the
            // fallback is a single LIMIT-1 query.
            $proyectoRevisado = $pivot?->proyecto
                ?? $entrega->firstProyecto();

            // Auto-advance phase if all entregas in the current phase of the
            // REVIEWED project are approved.
            if ($data['status'] === 'aprobada') {
                $this->autoAdvancePhase($entrega, $proyectoRevisado);
            }

            $this->notificarEstudiantes($proyectoRevisado, $entrega, $data, $senderId);

            // Keep the API payload shape: `proyectos` now exposes only the
            // reviewed project instead of every linked project of the semester.
            return $entrega->fresh()->setRelation(
                'proyectos',
                $proyectoRevisado !== null ? collect([$proyectoRevisado]) : collect()
            );
        });
    }

    /**
     * Notify ONLY the students of the reviewed project (issue #49). A single
     * bulk insert replaces one create() per student; Notificacion has no
     * observers (only ProyectoObserver exists), so insert() is safe.
     */
    private function notificarEstudiantes(?Proyecto $proyecto, Entrega $entrega, array $data, int $senderId): void
    {
        if ($proyecto === null) {
            return;
        }

        $estudianteIds = $proyecto->estudiantes()->pluck('user_id')->unique()->values();

        if ($estudianteIds->isEmpty()) {
            return;
        }

        $ahora = now();

        Notificacion::insert(
            $estudianteIds->map(fn (int $id): array => [
                'user_id' => $id,
                'sender_id' => $senderId,
                'type' => 'entrega.revisada',
                'title' => "Entrega {$data['status']}: {$entrega->title}",
                'content' => "Tu entrega '{$entrega->title}' ha sido {$data['status']}.",
                'is_read' => false,
                'sent_at' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all()
        );
    }

    /**
     * RF-NOT-03 / design TBD-6: the director can review an entrega as long
     * as it is not in a terminal status (aprobada/rechazada). The due_date
     * only restricts the STUDENT from uploading — it never blocks the
     * director, coordinator, or evaluator from reviewing, observing, or
     * grading.
     *
     * A project-scoped review reads the verdict from ITS pivot, because the
     * semester template's status is shared by every project of the entrega.
     * A pivot with no verdict yet (legacy data) falls back to the template
     * status, preserving the previous behaviour.
     */
    private function esEditable(Entrega $entrega, ?EntregaProyecto $entregaProyecto = null): bool
    {
        $terminal = ['aprobada', 'rechazada'];
        $estado = $entregaProyecto?->estado ?? $entrega->status?->value;

        return ! in_array($estado, $terminal, true);
    }

    /**
     * Auto-advance the phase of the REVIEWED project only when none of its
     * entregas in the current phase is still BLOCKING.
     *
     * "Blocking" is a PER-PROJECT verdict: a project-scoped review writes it
     * on the pivot and leaves `entregas.status` (the shared semester
     * template) untouched, so filtering this gate by the template column
     * would find a non-approved delivery forever and the project would never
     * advance. Pivots without a verdict still fall back to the template, the
     * behaviour that applied before the pivot existed.
     *
     * It is deliberately NOT "approved": a delivery whose deadline expired with
     * nothing uploaded is not approved, but it is not actionable either, and
     * keeping it in this gate froze the project forever while the final grade
     * had already closed. See `NotaEntregaResolver::bloqueaAvanceDeFase`.
     */
    private function autoAdvancePhase(Entrega $entrega, ?Proyecto $proyectoRevisado): void
    {
        if ($proyectoRevisado === null) {
            return;
        }

        // `due_date` and `hora_maxima` are read by the gate: a delivery past
        // its window with nothing uploaded must not hold the phase.
        $entregasDeLaFase = Entrega::paraProyecto($proyectoRevisado->id)
            ->where('phase', $entrega->phase)
            ->get(['id', 'status', 'due_date', 'hora_maxima']);

        // No entrega of this phase means the gate has nothing to measure, and
        // `contains()` over an empty collection is false — which read as
        // "nothing pending" and advanced a project that has no entrega in this
        // phase at all. Reachable through the legacy fallback in handle(),
        // where the reviewed project comes from the version's pivot and need
        // not match the reviewed entrega's project.
        if ($entregasDeLaFase->isEmpty()) {
            return;
        }

        $pivotes = EntregaProyecto::query()
            ->where('proyecto_id', $proyectoRevisado->id)
            ->whereIn('entrega_id', $entregasDeLaFase->pluck('id'))
            ->get()
            ->keyBy('entrega_id');

        $pendingInPhase = $entregasDeLaFase->contains(
            fn (Entrega $e) => $this->notas->bloqueaAvanceDeFase($e, $pivotes->get($e->id))
        );

        if (! $pendingInPhase) {
            $currentPhase = $proyectoRevisado->current_phase;

            if ($currentPhase->value === $entrega->phase) {
                $nextPhase = $currentPhase->next();

                if ($nextPhase !== null) {
                    $proyectoRevisado->current_phase = $nextPhase;
                    $proyectoRevisado->save();
                }
            }
        }
    }
}
