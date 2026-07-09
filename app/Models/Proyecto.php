<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property int|null $director_id
 * @property int $semester_id
 * @property FaseProyecto $current_phase
 * @property EstadoProyecto $status
 * @property bool $requires_group_justification
 * @property int $alert_count
 */
class Proyecto extends Model
{
    protected $table = 'proyectos';

    protected $fillable = [
        'title',
        'director_id',
        'semester_id',
        'current_phase',
        'status',
        'requires_group_justification',
    ];

    protected function casts(): array
    {
        return [
            'current_phase' => FaseProyecto::class,
            'status' => EstadoProyecto::class,
            'requires_group_justification' => 'boolean',
        ];
    }

    // -- relations -----------------------------------------------------

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class, 'semester_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_estudiante', 'proyecto_id', 'user_id')
            ->withTimestamps();
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class);
    }

    // -- scopes --------------------------------------------------------

    public function scopeEnSemestresActivos(Builder $query): Builder
    {
        return $query->whereHas('semestre', fn (Builder $q) => $q->where('is_active', true));
    }

    // -- boot ----------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function ($proyecto) {
            $semestre = Semestre::find($proyecto->semester_id);
            $semestreCode = str_replace('-', '', $semestre->name);
            $count = static::where('semester_id', $proyecto->semester_id)->count() + 1;
            $proyecto->code = 'PG-' . $semestreCode . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        });
    }
}
