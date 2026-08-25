<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEntrega;
use App\Enums\UserRole;
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
        'evaluation_metrics',
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
        // Canonical group: the entrega's own semester_id (semester FK).
        // Project-derived values are only a legacy fallback when it is null.
        if ($this->semester_id !== null) {
            return $this->semester_id;
        }

        if ($this->relationLoaded('proyectos') && $this->proyectos->isNotEmpty()) {
            return $this->proyectos->first()->semester_id;
        }

        return null;
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'entrega_proyecto', 'entrega_id', 'proyecto_id')
            ->withTimestamps();
    }

    /**
     * Get the first linked project (from pivot).
     */
    public function firstProyecto(): ?Proyecto
    {
        if ($this->relationLoaded('proyectos') && $this->proyectos->isNotEmpty()) {
            return $this->proyectos->first();
        }

        return $this->proyectos()->first();
    }

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class, 'semester_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(VersionDocumento::class, 'entrega_id');
    }

    public function analisisIa(): HasMany
    {
        return $this->hasMany(AiDocumentEvaluation::class, 'entrega_id');
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
     * Requested documents declared on this entrega (JSON configuration).
     *
     * @return array<int, array<string, mixed>>
     */
    public function documentosSolicitados(): array
    {
        $documentos = $this->archivos_requeridos ?? [];

        return is_array($documentos) ? $documentos : [];
    }

    /**
     * Identity (slug/id) of the single AI-analyzable document, if any.
     */
    public function idDocumentoAnalizableIa(): ?string
    {
        foreach ($this->documentosSolicitados() as $documento) {
            if (! filter_var($documento['analizable_ia'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $id = $documento['slug'] ?? $documento['id'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        return null;
    }

    /**
     * Whether the version belongs to the AI-analyzable requested document.
     */
    public function versionEsAnalizableIa(VersionDocumento $version): bool
    {
        $iaId = $this->idDocumentoAnalizableIa();

        if ($iaId === null) {
            return false;
        }

        $versionDocId = $version->archivo_requerido_id;

        if ($versionDocId === null || $versionDocId === '') {
            $first = $this->documentosSolicitados()[0] ?? null;
            $firstId = is_array($first) ? ($first['slug'] ?? $first['id'] ?? null) : null;

            return $firstId === $iaId;
        }

        return $versionDocId === $iaId;
    }

    /**
     * Find a requested document by its slug or id.
     *
     * @return array<string, mixed>|null
     */
    public function getArchivoRequerido(string $slug): ?array
    {
        foreach ($this->documentosSolicitados() as $archivo) {
            $id = $archivo['slug'] ?? $archivo['id'] ?? null;

            if ($id === $slug) {
                return $archivo;
            }
        }

        return null;
    }

    /**
     * Filter entregas by project — the pivot table is the single source of
     * truth for the entrega-project link.
     */
    public function scopeParaProyecto(Builder $query, int $proyectoId): Builder
    {
        return $query->whereHas('proyectos', function ($q2) use ($proyectoId) {
            $q2->where('proyectos.id', $proyectoId);
        });
    }

    /**
     * Scope entregas by what the user may list (issue #38, #47).
     *
     * Coordinador sees everything (intentional). Director, Estudiante and
     * EvaluadorExterno are filtered to their projects. Unknown roles
     * default to an empty result (default-deny). An external evaluator
     * lists only entregas linked to projects they are assigned to (issue
     * #47, hallazgo 3).
     */
    public function scopeParaUsuario(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Coordinador => $query,
            UserRole::Director => $query->whereHas('proyectos', fn ($sq) => $sq->where('director_id', $user->id)),
            UserRole::Estudiante => $query->whereHas('proyectos.estudiantes', fn ($sq) => $sq->where('user_id', $user->id)),
            UserRole::EvaluadorExterno => $query->whereHas('proyectos', fn ($sq) => $sq->whereIn(
                'proyectos.id',
                EvaluadorProyecto::where('evaluador_id', $user->id)->select('proyecto_id')
            )),
            default => $query->whereRaw('0 = 1'),
        };
    }

    /**
     * Whether the given user is a student of any project linked to this
     * entrega (via the pivot).
     */
    public function esEstudiante(int $userId): bool
    {
        return $this->proyectos()
            ->whereHas('estudiantes', fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }

    /**
     * Whether the given user is the director of any project linked to this
     * entrega (via the pivot).
     */
    public function esDirector(int $userId): bool
    {
        return $this->proyectos()
            ->where('director_id', $userId)
            ->exists();
    }
}
