<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function scopeUltima(Builder $query): Builder
    {
        return $query->orderByDesc('version_number');
    }
}
