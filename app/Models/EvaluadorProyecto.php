<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoInvitacionEvaluador;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluadorProyecto extends Model
{
    use HasFactory;

    protected $table = 'evaluador_proyecto';

    protected $fillable = [
        'proyecto_id',
        'evaluador_id',
        'invitation_status',
        'assigned_at',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'fase',
        'evaluado',
    ];

    protected function casts(): array
    {
        return [
            'invitation_status' => EstadoInvitacionEvaluador::class,
            'assigned_at' => 'datetime',
            'fecha' => 'date:Y-m-d',
            'hora_inicio' => 'string',
            'hora_fin' => 'string',
            'evaluado' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }
}
