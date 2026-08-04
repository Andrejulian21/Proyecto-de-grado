<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEntrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrega extends Model
{
    use HasFactory;

    protected $table = 'entregas';

    protected $fillable = [
        'proyecto_id',
        'semester_id',
        'phase',
        'title',
        'description',
        'due_date',
        'start_date',
        'start_time',
        'status',
        'consolidated_grade',
        'evaluation_complete',
        'grade_percentage',
        'director_grade',
        'acceptance_criteria',
        'hora_maxima',
        'archivos_requeridos',
    ];

    protected $appends = ['grupo_id'];

    protected function casts(): array
    {
        return [
            'status' => EstadoEntrega::class,
            'due_date' => 'date',
            'start_date' => 'date',
            'consolidated_grade' => 'decimal:2',
            'evaluation_complete' => 'boolean',
            'grade_percentage' => 'decimal:2',
            'director_grade' => 'decimal:2',
            'archivos_requeridos' => 'json',
        ];
    }

    public function getGrupoIdAttribute(): ?int
    {
        // Try direct proyecto FK first
        if ($this->proyecto) {
            return $this->proyecto->semester_id;
        }

        // Fall back to first pivot project's semester
        if ($this->relationLoaded('proyectos') && $this->proyectos->isNotEmpty()) {
            return $this->proyectos->first()->semester_id;
        }

        return $this->semester_id;
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'entrega_proyecto', 'entrega_id', 'proyecto_id')
            ->withTimestamps();
    }

    /**
     * Get the first linked project (from pivot) or fall back to direct proyecto relation.
     */
    public function firstProyecto(): ?Proyecto
    {
        if ($this->relationLoaded('proyectos') && $this->proyectos->isNotEmpty()) {
            return $this->proyectos->first();
        }

        return $this->proyecto;
    }

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class, 'semester_id');
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

    /**
     * Find a specific archivo requerido by its slug.
     */
    public function getArchivoRequerido(string $slug): ?array
    {
        $archivos = $this->archivos_requeridos ?? [];

        if (! is_array($archivos)) {
            return null;
        }

        foreach ($archivos as $archivo) {
            if (($archivo['slug'] ?? null) === $slug) {
                return $archivo;
            }
        }

        return null;
    }

    /**
     * Filter entregas by project — checks both direct FK and pivot table.
     */
    public function scopeParaProyecto(Builder $query, int $proyectoId): Builder
    {
        return $query->where(function ($q) use ($proyectoId) {
            $q->where('proyecto_id', $proyectoId)
                ->orWhereHas('proyectos', function ($q2) use ($proyectoId) {
                    $q2->where('proyectos.id', $proyectoId);
                });
        });
    }
}
