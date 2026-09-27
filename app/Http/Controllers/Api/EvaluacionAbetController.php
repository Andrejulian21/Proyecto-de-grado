<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use App\Exceptions\DocumentEvaluationException;
use App\Http\Controllers\Controller;
use App\Models\AiDocumentEvaluation;
use App\Models\VersionDocumento;
use App\Services\Evaluation\Access\DirectorEntregaAccessResolver;
use App\Services\Evaluation\AiFeedbackPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only director view of the AI analysis requested by the student.
 *
 * The student-requested analysis (type pre_submission) is shown on the
 * delivery from the director side. The director never re-runs the analysis
 * here: there is no POST endpoint on purpose.
 *
 * Group rule: with ?proyecto_id, the latest completed student analysis
 * attributed to that project group is returned (official versions linked
 * to the group pivot, or temporal analyses authored by a group student).
 * Without ?proyecto_id, the latest completed student analysis of the
 * entrega is returned. When the student has not requested any analysis
 * yet, data is null.
 */
class EvaluacionAbetController extends Controller
{
    public function __construct(
        private readonly DirectorEntregaAccessResolver $access,
    ) {}

    /**
     * GET /api/director/entregas/{entrega}/evaluacion-abet
     */
    public function show(Request $request, int $entrega): JsonResponse
    {
        try {
            $this->access->resolve($request->user(), $entrega);
        } catch (DocumentEvaluationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }

        $versionId = $request->query('version_id');
        $versionId = $versionId !== null && $versionId !== '' ? (int) $versionId : null;
        $proyectoId = $request->query('proyecto_id');
        $proyectoId = $proyectoId !== null && $proyectoId !== '' ? (int) $proyectoId : null;

        if ($versionId !== null) {
            $versionExists = VersionDocumento::query()
                ->where('entrega_id', $entrega)
                ->where('id', $versionId)
                ->exists();

            if (! $versionExists) {
                return response()->json([
                    'error' => 'No se encontró la versión del documento.',
                    'code' => 'not_found',
                ], 404);
            }
        }

        $historial = AiDocumentEvaluation::query()
            ->where('entrega_id', $entrega)
            ->where('type', AiEvaluationType::PreSubmission)
            ->where('status', AiEvaluationStatus::Completed)
            ->when($proyectoId !== null, function ($query) use ($proyectoId) {
                $query->where(function ($scoped) use ($proyectoId) {
                    $scoped->whereHas('versionDocumento.entregaProyecto', function ($pivot) use ($proyectoId) {
                        $pivot->where('proyecto_id', $proyectoId);
                    })->orWhereHas('user.proyectosComoEstudiante', function ($members) use ($proyectoId) {
                        $members->where('proyectos.id', $proyectoId);
                    });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $latest = $historial->first();

        if (! $latest) {
            return response()->json([
                'data' => null,
                'historial' => [],
            ]);
        }

        return response()->json([
            'data' => AiFeedbackPresenter::toArray($latest),
            'historial' => $historial->map(fn (AiDocumentEvaluation $row) => AiFeedbackPresenter::toArray($row))->values(),
        ]);
    }
}
