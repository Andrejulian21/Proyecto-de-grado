<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Enums\AiEvaluationStatus;
use App\Models\AiDocumentEvaluation;
use App\Models\VersionDocumento;
use Illuminate\Support\Facades\Storage;

/**
 * Link pre-version (temporary) analyses to an official version only when the
 * uploaded file is the same document (sha256 match). Never invents a version.
 */
final class AttachTemporaryAiFeedbackToVersion
{
    public function handle(VersionDocumento $version): void
    {
        $documentoId = $version->archivo_requerido_id;

        if (! is_string($documentoId) || $documentoId === '') {
            return;
        }

        $absolute = Storage::disk('public')->path($version->file_path);

        if (! is_file($absolute)) {
            return;
        }

        $hash = hash_file('sha256', $absolute);

        if (! is_string($hash) || $hash === '') {
            return;
        }

        AiDocumentEvaluation::query()
            ->where('entrega_id', $version->entrega_id)
            ->where('archivo_requerido_id', $documentoId)
            ->whereNull('version_documento_id')
            ->where('status', AiEvaluationStatus::Completed)
            ->where('document_hash', $hash)
            ->update(['version_documento_id' => $version->id]);
    }
}
