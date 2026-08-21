<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Models\AiDocumentEvaluation;

final class AiFeedbackPresenter
{
    /**
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     */
    public static function toArray(AiDocumentEvaluation $evaluation, ?array $result = null): array
    {
        $type = $evaluation->type;
        $status = $evaluation->status;

        return [
            'id' => $evaluation->id,
            'entrega_id' => $evaluation->entrega_id,
            'documento_id' => $evaluation->archivo_requerido_id,
            'version_id' => $evaluation->version_documento_id,
            'temporal' => $evaluation->version_documento_id === null,
            'tipo' => $type->value,
            'estado' => $status->value,
            'proveedor' => $evaluation->provider,
            'tiempo_ms' => $evaluation->processing_ms,
            'resultado' => $result ?? $evaluation->result_json,
            'analizado_en' => $evaluation->created_at?->toIso8601String(),
        ];
    }
}
