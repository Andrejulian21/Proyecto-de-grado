<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiEvaluationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersionDocumento extends Model
{
    protected $table = 'versiones_documento';

    protected $fillable = [
        'entrega_id',
        'version_number',
        'file_path',
        'file_size',
        'original_name',
        'director_notes',
        'uploaded_at',
        'entrega_proyecto_id',
        'archivo_requerido_id',
        'descontinuado',
    ];

    protected function casts(): array
    {
        return [
            'descontinuado' => 'boolean',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    public function entregaProyecto(): BelongsTo
    {
        return $this->belongsTo(EntregaProyecto::class, 'entrega_proyecto_id');
    }

    public function analisisIa(): HasMany
    {
        return $this->hasMany(AiDocumentEvaluation::class, 'version_documento_id')
            ->where('status', AiEvaluationStatus::Completed->value)
            ->orderByDesc('created_at');
    }

    public function scopeUltima(Builder $query): Builder
    {
        return $query->orderByDesc('version_number');
    }

    /**
     * Restrict versions to the deliveries of one or more projects.
     *
     * An `entrega` is a semester-wide template shared by every linked project,
     * but a version belongs to exactly ONE of them: `entrega_proyecto_id` is
     * the only link between an uploaded document and the project that owns it.
     * Filtering through it is therefore the isolation boundary — filtering by
     * `entrega_id` alone leaks every project's uploads to all of them.
     *
     * Versions whose pivot was cleared (`ON DELETE SET NULL`) belong to no
     * project and are excluded by every call.
     *
     * @param  int|list<int>  $proyectoIds
     */
    public function scopeParaProyecto(Builder $query, int|array $proyectoIds): Builder
    {
        $ids = array_values(array_filter(array_map('intval', (array) $proyectoIds)));

        if ($ids === []) {
            // Default-deny: with no project there is nothing to own a version.
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'entrega_proyecto_id',
            EntregaProyecto::query()->whereIn('proyecto_id', $ids)->select('id'),
        );
    }
}
