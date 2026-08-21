<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\VersionDocumento;

/**
 * Single-purpose use case: the director approves/rejects an entrega with a
 * grade and feedback, auto-advances the project phase and notifies the
 * students of every linked project.
 */
final class ReviewEntregaAction
{
    /**
     * @param  array<string, mixed>  $data  validated review payload
     */
    public function handle(Entrega $entrega, array $data, int $senderId): Entrega
    {
        // RF-NOT-03: the director may only review/edit an entrega that is
        // still open (non-terminal status and due date not passed).
        if (! $this->esEditable($entrega)) {
            throw new EntregaActionException('La entrega está cerrada; la nota y las observaciones no pueden modificarse', 422);
        }

        $updateData = [
            'status' => $data['status'],
            'consolidated_grade' => $data['consolidated_grade'] ?? null,
            'evaluation_complete' => true,
        ];

        $entrega->update($updateData);

        // Resolve the reviewed version (RF-NOT-02: notes are per version).
        $version = VersionDocumento::where('entrega_id', $entrega->id)
            ->where('id', $data['version_id'])
            ->firstOrFail();

        // Observations belong to the selected version of any requested document.
        if (array_key_exists('director_notes', $data) && $data['director_notes'] !== null && $data['director_notes'] !== '') {
            $version->update(['director_notes' => $data['director_notes']]);
        }

        // D3-rev: the director grade and observations belong to the STUDENT
        // delivery (per project). The reviewed version resolves its
        // EntregaProyecto; legacy versions without a pivot are skipped.
        $entregaProyecto = $version->entregaProyecto;

        if ($entregaProyecto !== null) {
            $pivotData = [];

            // RF-NOT-02: the grade is only captured when the delivery is
            // approved; it is never persisted on a non-approval review.
            if ($data['status'] === 'aprobada' && isset($data['director_grade'])) {
                $pivotData['director_grade'] = $data['director_grade'];
            }

            if (array_key_exists('director_notes', $data) && $data['director_notes'] !== null && $data['director_notes'] !== '') {
                $pivotData['observaciones_director'] = $data['director_notes'];
            }

            if ($pivotData !== []) {
                $entregaProyecto->update($pivotData);
            }
        }

        // Auto-advance phase if all entregas in the current phase are approved
        if ($data['status'] === 'aprobada') {
            $this->autoAdvancePhase($entrega);
        }

        $entrega->load('proyecto:id,code,title,current_phase', 'proyectos:id,code,title,current_phase');

        // Notify students of all linked projects
        $proyectos = $entrega->proyectos->isNotEmpty() ? $entrega->proyectos : collect([$entrega->proyecto])->filter();
        $notifiedUserIds = collect();

        foreach ($proyectos as $proyecto) {
            $estudiantes = $proyecto->estudiantes()->pluck('user_id');

            foreach ($estudiantes as $estudianteId) {
                if ($notifiedUserIds->has($estudianteId)) {
                    continue;
                }
                $notifiedUserIds->put($estudianteId, true);
                Notificacion::create([
                    'user_id' => $estudianteId,
                    'sender_id' => $senderId,
                    'type' => 'entrega.revisada',
                    'title' => "Entrega {$data['status']}: {$entrega->title}",
                    'content' => "Tu entrega '{$entrega->title}' ha sido {$data['status']}.",
                    'sent_at' => now(),
                ]);
            }
        }

        return $entrega->fresh();
    }

    /**
     * RF-NOT-03 / design TBD-6: an entrega is editable while it is not in a
     * terminal status (aprobada/rechazada) and its due date has not passed.
     * There is no literal 'activa' state in the enum; openness is derived
     * from the negation of the terminal states.
     */
    private function esEditable(Entrega $entrega): bool
    {
        $terminal = ['aprobada', 'rechazada'];

        return ! in_array($entrega->status?->value, $terminal, true)
            && $entrega->due_date !== null
            && $entrega->due_date->startOfDay()->gte(now()->startOfDay());
    }

    /**
     * Auto-advance the project phase when all entregas in the current
     * phase are approved.
     */
    private function autoAdvancePhase(Entrega $entrega): void
    {
        $proyectos = $entrega->proyectos()->get();

        // Fall back to direct proyecto relation if no pivot projects
        if ($proyectos->isEmpty() && $entrega->proyecto) {
            $proyectos = collect([$entrega->proyecto]);
        }

        foreach ($proyectos as $proyecto) {
            // Check if there are any non-approved entregas in this phase for this project
            // (scope checks both direct FK and pivot table)
            $pendingInPhase = Entrega::paraProyecto($proyecto->id)
                ->where('phase', $entrega->phase)
                ->where('status', '!=', 'aprobada')
                ->exists();

            if (! $pendingInPhase) {
                $currentPhase = $proyecto->current_phase;

                if ($currentPhase->value === $entrega->phase) {
                    $nextPhase = $currentPhase->next();

                    if ($nextPhase !== null) {
                        $proyecto->current_phase = $nextPhase;
                        $proyecto->save();
                    }
                }
            }
        }
    }
}
