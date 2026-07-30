<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoObservacion extends Model
{
    protected $table = 'seguimiento_observaciones';

    protected $fillable = [
        'proyecto_id',
        'semestre_id',
        'fase',
        'observacion',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class);
    }
}
