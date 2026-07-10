<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Whitelist entry: an email authorized to log in via Google OAuth and the
 * role to assign at first login.
 *
 * Stubs in PR 1; full CRUD + authorization in PR 2 (T-013 / T-020).
 *
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property UserRole $role
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AuthorizedEmail extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'name',
        'role',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    /**
     * The coordinador (User) who added this email to the whitelist.
     * Nullable to support system-seeded rows.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
