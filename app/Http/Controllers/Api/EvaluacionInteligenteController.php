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
use App\Services\Evaluation\Access\StudentProjectAccessResolver;
use App\Services\Evaluation\AiFeedbackPresenter;
use App\Services\Evaluation\DocumentEvaluationService;
use App\Services\Evaluation\Interpreters\PreSubmissionResultInterpreter;
use App\Services\Evaluation\Strategies\PreSubmissionDeliveryStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student-facing intelligent pre-submission evaluation.
 * Accepts either an official version_id OR a temporary DOCX upload (never stored as VersionDocumento).
 */
class EvaluacionInteligenteController extends Controller
{
    public function __construct(
        private readonly DocumentEvaluationService $evaluationService,
        private readonly PreSubmissionDeliveryStrategy $strategy,
        private readonly StudentProjectAccessResolver $access,
        private readonly PreSubmissionResultInterpreter $interpreter,
    ) {}

    /**
     * GET /api/estudiante/entregas/{entrega}/evaluacion-inteligente
     */
    public function index(Request $request, int $entrega): JsonResponse
    {
        try {
            $this->access->resolve($request->user(), $entrega);
        } catch (DocumentEvaluationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }

        $versionId = $this->optionalVersionId($request->query('version_id'));

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

        return $this->historialResponse($entrega, $versionId);
    }

    /**
     * POST /api/estudiante/entregas/{entrega}/evaluacion-inteligente
     */
    public function store(Request $request, int $entrega): JsonResponse
    {
        $validated = $request->validate([
            'version_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'required_without:file'],
            'file' => [
                'sometimes',
                'nullable',
                'file',
                'required_without:version_id',
                'mimes:docx',
                'max:10240',
            ],
        ]);

        $temporaryFile = $request->file('file');
        $versionId = isset($validated['version_id']) ? (int) $validated['version_id'] : null;

        if ($temporaryFile === null && $versionId === null) {
            return response()->json([
                'error' => 'Debes adjuntar un archivo DOCX temporal o indicar una versión.',
                'code' => 'invalid_request',
            ], 422);
        }

        try {
            $outcome = $this->evaluationService->evaluate(
                user: $request->user(),
                entregaId: $entrega,
                strategy: $this->strategy,
                access: $this->access,
                interpreter: $this->interpreter,
                versionId: $temporaryFile !== null ? null : $versionId,
                temporaryFile: $temporaryFile,
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

    private function historialResponse(int $entregaId, ?int $versionId): JsonResponse
    {
        $historial = AiDocumentEvaluation::query()
            ->where('entrega_id', $entregaId)
            ->where('status', AiEvaluationStatus::Completed)
            ->when($versionId !== null, fn ($query) => $query->where('version_documento_id', $versionId))
            ->orderByDesc('created_at')
            ->get();

        $latest = $historial->first();

        return response()->json([
            'data' => $latest ? AiFeedbackPresenter::toArray($latest) : null,
            'historial' => $historial->map(fn (AiDocumentEvaluation $row) => AiFeedbackPresenter::toArray($row))->values(),
        ]);
    }

    private function optionalVersionId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
