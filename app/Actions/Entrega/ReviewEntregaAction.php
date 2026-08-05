<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

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
        $entrega->update([
            'status' => $data['status'],
            'consolidated_grade' => $data['consolidated_grade'] ?? null,
            'evaluation_complete' => true,
        ]);

        // Save director notes to the specific version sent by the frontend
        if (! empty($data['director_notes'])) {
            $version = VersionDocumento::where('entrega_id', $entrega->id)
                ->where('id', $data['version_id'])
                ->firstOrFail();

            $version->update(['director_notes' => $data['director_notes']]);
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
