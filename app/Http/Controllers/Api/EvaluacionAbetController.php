<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiErrorCode;
use App\Enums\AiEvaluationStatus;
use App\Exceptions\AiException;
use App\Exceptions\DocumentEvaluationException;
use App\Http\Controllers\Controller;
use App\Models\AiDocumentEvaluation;
use App\Models\VersionDocumento;
use App\Services\Evaluation\Access\DirectorEntregaAccessResolver;
use App\Services\Evaluation\AiFeedbackPresenter;
use App\Services\Evaluation\DocumentEvaluationService;
use App\Services\Evaluation\Interpreters\PreSubmissionResultInterpreter;
use App\Services\Evaluation\Strategies\AbetDirectorEvaluationStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Director-facing preliminary document analysis.
 * Reuses DocumentEvaluationService and the shared preliminary prompt; never talks to AI vendors.
 */
class EvaluacionAbetController extends Controller
{
    public function __construct(
        private readonly DocumentEvaluationService $evaluationService,
        private readonly AbetDirectorEvaluationStrategy $strategy,
        private readonly DirectorEntregaAccessResolver $access,
        private readonly PreSubmissionResultInterpreter $interpreter,
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

        if ($versionId !== null) {
            $exists = VersionDocumento::query()
                ->where('entrega_id', $entrega)
                ->where('id', $versionId)
                ->exists();

            if (! $exists) {
                return response()->json([
                    'error' => 'No se encontró la versión del documento.',
                    'code' => 'not_found',
                ], 404);
            }
        }

        $historial = AiDocumentEvaluation::query()
            ->where('entrega_id', $entrega)
            ->where('status', AiEvaluationStatus::Completed)
            ->when($versionId !== null, fn ($query) => $query->where('version_documento_id', $versionId))
            ->orderByDesc('created_at')
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

    /**
     * POST /api/director/entregas/{entrega}/evaluacion-abet
     */
    public function store(Request $request, int $entrega): JsonResponse
    {
        $validated = $request->validate([
            'version_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $outcome = $this->evaluationService->evaluate(
                user: $request->user(),
                entregaId: $entrega,
                strategy: $this->strategy,
                access: $this->access,
                interpreter: $this->interpreter,
                versionId: isset($validated['version_id']) ? (int) $validated['version_id'] : null,
            );

            return response()->json([
                'data' => AiFeedbackPresenter::toArray($outcome['evaluation'], $outcome['result']),
            ]);
        } catch (DocumentEvaluationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        } catch (AiException $exception) {
            if (in_array($exception->error, [
                AiErrorCode::ProviderNotConfigured,
                AiErrorCode::UnknownProvider,
            ], true)) {
                return response()->json([
                    'error' => 'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.',
                    'code' => 'ai_unavailable',
                ], 503);
            }

            return response()->json([
                'error' => 'No fue posible completar el análisis con el servicio de Inteligencia Artificial.',
                'code' => $exception->error->value,
            ], 502);
        }
    }
}
