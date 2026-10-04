<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeveridadAlerta;
use App\Enums\TipoAlerta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A persistent operational alert.
 *
 * Rows are produced by `App\Services\Alertas\AlertaGenerator`, which upserts by
 * `clave` and deletes whatever stopped applying. `reviewed_at` is the only
 * state a human owns: once set, the alert stops counting towards the
 * coordinator KPI and leaves the default listing.
 *
 * @property int $id
 * @property string $clave
 * @property TipoAlerta $tipo
 * @property string $mensaje
 * @property int|null $proyecto_id
 * @property SeveridadAlerta $severidad
 * @property array<string, mixed>|null $datos
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 */
class Alerta extends Model
{
    use HasFactory;

    protected $table = 'alertas';

    protected $fillable = [
        'clave',
        'tipo',
        'mensaje',
        'proyecto_id',
        'severidad',
        'datos',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAlerta::class,
            'severidad' => SeveridadAlerta::class,
            'datos' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeNoRevisadas(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at');
    }

    public function scopeRevisadas(Builder $query): Builder
    {
        return $query->whereNotNull('reviewed_at');
    }

    public function scopePorTipo(Builder $query, TipoAlerta $tipo): Builder
    {
        return $query->where('tipo', $tipo->value);
    }

    public function estaRevisada(): bool
    {
        return $this->reviewed_at !== null;
    }
}
