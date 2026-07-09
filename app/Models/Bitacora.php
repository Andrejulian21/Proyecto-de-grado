<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFirma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacoras';

    protected $fillable = [
        'proyecto_id',
        'topic',
        'notes',
        'evidence_file',
        'meeting_date',
        'signature_status',
        'student_signed_at',
        'director_signed_at',
        'duration_hours',
    ];

    protected function casts(): array
    {
        return [
            'signature_status' => EstadoFirma::class,
            'meeting_date' => 'date',
            'student_signed_at' => 'datetime',
            'director_signed_at' => 'datetime',
            'duration_hours' => 'decimal:2',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function scopePorEstado(Builder $query, string $status): Builder
    {
        return $query->where('signature_status', $status);
    }
}
