<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiErrorCode;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use App\Exceptions\AiException;
use App\Exceptions\DocumentEvaluationException;
use App\Http\Controllers\Controller;
use App\Models\AiDocumentEvaluation;
use App\Services\Evaluation\Access\DirectorEntregaAccessResolver;
use App\Services\Evaluation\DocumentEvaluationService;
use App\Services\Evaluation\Interpreters\AbetEvaluationResultInterpreter;
use App\Services\Evaluation\Metrics\PlaceholderAbetMetricsDefinition;
use App\Services\Evaluation\Strategies\AbetDirectorEvaluationStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Director-facing ABET-oriented document evaluation.
 * Reuses DocumentEvaluationService; never talks to AI vendors.
 */
class EvaluacionAbetController extends Controller
{
    public function __construct(
        private readonly DocumentEvaluationService $evaluationService,
        private readonly AbetDirectorEvaluationStrategy $strategy,
        private readonly DirectorEntregaAccessResolver $access,
        private readonly PlaceholderAbetMetricsDefinition $metrics,
    ) {}

    /**
     * GET /api/director/entregas/{entrega}/evaluacion-abet
     * Latest completed ABET evaluation for the entrega (director-scoped).
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

        $evaluation = AiDocumentEvaluation::query()
            ->where('entrega_id', $entrega)
            ->where('type', AiEvaluationType::Abet)
            ->where('status', AiEvaluationStatus::Completed)
            ->orderByDesc('created_at')
            ->first();

        if (! $evaluation) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->mapEvaluation($evaluation),
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

        $interpreter = new AbetEvaluationResultInterpreter($this->metrics);

        try {
            $outcome = $this->evaluationService->evaluate(
                user: $request->user(),
                entregaId: $entrega,
                strategy: $this->strategy,
                access: $this->access,
                interpreter: $interpreter,
                versionId: isset($validated['version_id']) ? (int) $validated['version_id'] : null,
            );

            return response()->json([
                'data' => $this->mapEvaluation($outcome['evaluation'], $outcome['result']),
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

    /**
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     */
    private function mapEvaluation(AiDocumentEvaluation $evaluation, ?array $result = null): array
    {
        return [
            'id' => $evaluation->id,
            'entrega_id' => $evaluation->entrega_id,
            'version_id' => $evaluation->version_documento_id,
            'tipo' => $evaluation->type->value,
            'estado' => $evaluation->status->value,
            'proveedor' => $evaluation->provider,
            'tiempo_ms' => $evaluation->processing_ms,
            'perfil_metricas' => is_array($result)
                ? ($result['perfil_metricas'] ?? null)
                : (is_array($evaluation->result_json) ? ($evaluation->result_json['perfil_metricas'] ?? null) : null),
            'resultado' => $result ?? $evaluation->result_json,
        ];
    }
}
