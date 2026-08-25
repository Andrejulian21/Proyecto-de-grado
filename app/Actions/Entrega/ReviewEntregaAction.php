<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\Proyecto;
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

        // The reviewed project is the one owning the reviewed version's
        // per-project delivery (EntregaProyecto). Legacy versions without a
        // pivot fall back to the entrega's first linked project.
        $proyectoRevisado = $entregaProyecto?->proyecto
            ?? $entrega->proyectos->first();

        // Auto-advance phase if all entregas in the current phase are approved
        if ($data['status'] === 'aprobada') {
            $this->autoAdvancePhase($entrega, $proyectoRevisado);
        }

        $entrega->load('proyectos:id,code,title,current_phase');

        // Notify ONLY the students of the reviewed project (resolved from the
        // reviewed version's per-project delivery). Legacy versions without a
        // pivot fall back to the entrega's first linked project.
        $proyectos = $proyectoRevisado !== null
            ? collect([$proyectoRevisado])
            : collect();
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
     * Auto-advance the phase of the REVIEWED project only when all its
     * entregas in the current phase are approved.
     */
    private function autoAdvancePhase(Entrega $entrega, ?Proyecto $proyectoRevisado): void
    {
        if ($proyectoRevisado === null) {
            return;
        }

        // Check if there are any non-approved entregas in this phase for this
        // project (scope only uses the pivot table).
        $pendingInPhase = Entrega::paraProyecto($proyectoRevisado->id)
            ->where('phase', $entrega->phase)
            ->where('status', '!=', 'aprobada')
            ->exists();

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
