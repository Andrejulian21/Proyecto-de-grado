<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentEvaluation extends Model
{
    protected $table = 'ai_document_evaluations';

    protected $fillable = [
        'user_id',
        'entrega_id',
        'version_documento_id',
        'archivo_requerido_id',
        'type',
        'status',
        'provider',
        'model',
        'document_hash',
        'prompt_version',
        'was_truncated',
        'original_chars',
        'kept_chars',
        'processing_ms',
        'result_json',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'type' => AiEvaluationType::class,
            'status' => AiEvaluationStatus::class,
            'result_json' => 'array',
            'was_truncated' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function versionDocumento(): BelongsTo
    {
        return $this->belongsTo(VersionDocumento::class, 'version_documento_id');
    }
}
