<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiErrorCode;
use App\Exceptions\AiException;
use App\Exceptions\DocumentEvaluationException;
use App\Http\Controllers\Controller;
use App\Services\Evaluation\Access\StudentProjectAccessResolver;
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

            $evaluation = $outcome['evaluation'];
            $result = $outcome['result'];

            return response()->json([
                'data' => [
                    'id' => $evaluation->id,
                    'entrega_id' => $evaluation->entrega_id,
                    'version_id' => $evaluation->version_documento_id,
                    'temporal' => $evaluation->version_documento_id === null,
                    'tipo' => $evaluation->type->value,
                    'estado' => $evaluation->status->value,
                    'proveedor' => $evaluation->provider,
                    'tiempo_ms' => $evaluation->processing_ms,
                    'resultado' => $result,
                ],
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
