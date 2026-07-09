<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecursoInformativo extends Model
{
    protected $table = 'recursos_informativos';

    protected $attributes = [
        'access_count' => 0,
    ];

    protected $fillable = [
        'author_id',
        'title',
        'category',
        'description',
        'file_path',
        'link',
        'access_count',
    ];

    protected function casts(): array
    {
        return [
            'access_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
