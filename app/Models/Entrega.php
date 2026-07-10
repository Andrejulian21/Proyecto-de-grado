<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEntrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrega extends Model
{
    use HasFactory;

    protected $table = 'entregas';

    protected $fillable = [
        'proyecto_id',
        'phase',
        'title',
        'description',
        'due_date',
        'status',
        'consolidated_grade',
        'evaluation_complete',
    ];

    protected function casts(): array
    {
        return [
            'status' => EstadoEntrega::class,
            'due_date' => 'date',
            'consolidated_grade' => 'decimal:2',
            'evaluation_complete' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(VersionDocumento::class, 'entrega_id');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'entrega_id');
    }

    public function scopePorFase(Builder $query, string $phase): Builder
    {
        return $query->where('phase', $phase);
    }

    public function scopePorEstado(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
