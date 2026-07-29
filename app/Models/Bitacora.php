<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFirma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class Bitacora extends Model
{
    use HasFactory;

    protected $table = 'bitacoras';

    protected $fillable = [
        'proyecto_id',
        'topic',
        'notes',
        'evidence_file',
        'meeting_date',
        'signature_status',
        'signature_code',
        'signature_code_expires_at',
        'signature_retries',
        'student_signed_at',
        'director_signed_at',
        'duration_hours',
    ];

    protected function casts(): array
    {
        return [
            'signature_status' => EstadoFirma::class,
            'signature_code_expires_at' => 'datetime',
            'meeting_date' => 'datetime',
            'student_signed_at' => 'datetime',
            'director_signed_at' => 'datetime',
            'duration_hours' => 'decimal:2',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function scopePorEstado(Builder $query, string $status): Builder
    {
        return $query->where('signature_status', $status);
    }

    /**
     * PR 1 — RF-SIG-04: a signature is considered valid when the director
     * has signed the bitacora (`signature_status === FirmadaDirector`).
     */
    public function hasValidSignature(): bool
    {
        return $this->signature_status === EstadoFirma::FirmadaDirector;
    }

    /**
     * PR 1 — RF-SIG-01: generate a fresh 6-digit code, store its hash, set
     * the expiration timestamp to now + 2 minutes, and return the plain
     * code so the caller can show it to the director.
     */
    public function generateSignatureCode(): string
    {
        $plain = (string) random_int(100_000, 999_999);

        $this->signature_code = Hash::make($plain);
        $this->signature_code_expires_at = now()->addMinutes(2);
        $this->save();

        return $plain;
    }

    /**
     * PR 1 — RF-SIG-03: a code may be re-requested at most once, and only
     * after the bitacora has been transitioned to `NoFirmada` (either by
     * exhausting the 5 attempts or by the 2-minute window expiring).
     */
    public function canResendCode(): bool
    {
        return $this->signature_status === EstadoFirma::NoFirmada
            && (int) $this->signature_retries < 1;
    }
}
