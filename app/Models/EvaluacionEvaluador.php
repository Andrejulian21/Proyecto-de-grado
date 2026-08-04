<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EvaluacionEvaluador — the immutable grade + observations that an
 * external evaluator submits for an assignment.
 *
 * One row per EvaluadorProyecto. The DB enforces uniqueness via the
 * UNIQUE(evaluador_proyecto_id) constraint added in
 * 2026_08_04_000003. Re-submission by the evaluator is rejected with a
 * 409 in the controller layer before it ever reaches the DB.
 *
 * @property int $id
 * @property int $evaluador_proyecto_id
 * @property string $nota
 * @property string|null $observaciones
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EvaluacionEvaluador extends Model
{
    use HasFactory;

    protected $table = 'evaluaciones_evaluador';

    protected $fillable = [
        'evaluador_proyecto_id',
        'nota',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'decimal:2',
        ];
    }

    public function evaluadorProyecto(): BelongsTo
    {
        return $this->belongsTo(EvaluadorProyecto::class, 'evaluador_proyecto_id');
    }
}
