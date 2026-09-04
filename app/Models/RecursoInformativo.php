<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RecursoInformativo extends Model
{
    use HasFactory;

    protected $table = 'recursos_informativos';

    protected $attributes = [
        'access_count' => 0,
    ];

    protected $appends = ['file_size'];

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

    public function getFileSizeAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        $bytes = Storage::disk('public')->size($this->file_path);

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, max(0, $i - 1)).' '.$units[$i];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
