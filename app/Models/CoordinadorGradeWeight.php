<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordinadorGradeWeight extends Model
{
    protected $table = 'coordinador_grade_weights';

    protected $fillable = [
        'semestre_id',
        'tipo',
        'peso_entregas',
        'peso_evaluadores',
        'peso_presentacion',
    ];

    protected function casts(): array
    {
        return [
            'peso_entregas' => 'decimal:2',
            'peso_evaluadores' => 'decimal:2',
            'peso_presentacion' => 'decimal:2',
        ];
    }

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class, 'semestre_id');
    }
}
