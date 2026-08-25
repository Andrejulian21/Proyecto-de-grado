<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEntrega;
use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
    use HasFactory;

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

    public function entregasPivot(): BelongsToMany
    {
        return $this->belongsToMany(Entrega::class, 'entrega_proyecto', 'proyecto_id', 'entrega_id')
            ->withTimestamps();
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class);
    }

    public function entregaProyectos(): HasMany
    {
        return $this->hasMany(EntregaProyecto::class, 'proyecto_id');
    }

    public function evaluadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evaluador_proyecto', 'proyecto_id', 'evaluador_id');
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

            // Issue #51 — Defect 3: serialize code generation per semester
            // with a PostgreSQL advisory lock (transaction-scoped). Two
            // concurrent creations for the same semester would otherwise
            // read the same count and generate the same sequential code,
            // tripping the `code` UNIQUE constraint with a 500.
            // The lock is only meaningful inside a transaction, so callers
            // must wrap the creation in DB::transaction (ProyectoController::store
            // does). On SQLite (tests) no lock exists — single-threaded anyway.
            if (DB::getDriverName() === 'pgsql') {
                DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', [
                    'proyecto-semestre-'.$proyecto->semester_id,
                ]);
            }

            $count = static::where('semester_id', $proyecto->semester_id)->count() + 1;
            $proyecto->code = 'PG-'.$semestreCode.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        });

        // Auto-vincular proyecto a todas las entregas existentes del semestre
        // cuando se crea un nuevo proyecto.
        static::created(function ($proyecto) {
            $entregas = Entrega::where('semester_id', $proyecto->semester_id)
                ->whereIn('status', [
                    EstadoEntrega::Pendiente->value,
                    EstadoEntrega::Enviada->value,
                    EstadoEntrega::Creada->value,
                    EstadoEntrega::Solicitada->value,
                    EstadoEntrega::Revisada->value,
                    EstadoEntrega::Aprobada->value,
                ])
                ->pluck('id');

            if ($entregas->isNotEmpty()) {
                $proyecto->entregasPivot()->attach($entregas);
            }
        });
    }
}
