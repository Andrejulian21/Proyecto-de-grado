<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectorAcademicProfile extends Model
{
    protected $table = 'director_academic_profiles';

    protected $fillable = [
        'user_id',
        'research_lines',
        'technologies',
        'methodologies',
        'academic_experience',
        'years_of_experience',
    ];

    protected function casts(): array
    {
        return [
            'research_lines' => 'array',
            'technologies' => 'array',
            'methodologies' => 'array',
            'years_of_experience' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
