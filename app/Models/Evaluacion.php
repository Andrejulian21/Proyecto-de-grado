<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'entrega_id',
        'evaluador_id',
        'criterio',
        'percentage',
        'grade',
        'comment',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'grade' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }
}
