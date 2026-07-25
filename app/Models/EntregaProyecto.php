<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntregaProyecto extends Model
{
    protected $table = 'entrega_proyecto';

    protected $fillable = [
        'entrega_id',
        'proyecto_id',
        'estado',
        'observaciones_director',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'string',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(VersionDocumento::class, 'entrega_proyecto_id');
    }
}
