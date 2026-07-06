<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles recognized across the UNAB project-grade platform.
 *
 * - `Estudiante`, `Director`, `Coordinador` are UNAB internal users who log
 *   in via Google OAuth (@unab.edu.co).
 * - `EvaluadorExterno` is a coordinator-managed account that logs in via
 *   email + password at /login/externo.
 *
 * The string backing value is the database representation; it is what
 * `users.role` stores. We intentionally keep the value equal to the
 * case name so JSON dumps and API responses are stable.
 */
enum UserRole: string
{
    case Estudiante = 'Estudiante';
    case Director = 'Director';
    case Coordinador = 'Coordinador';
    case EvaluadorExterno = 'EvaluadorExterno';

    /**
     * All string values, useful for validation rule strings:
     * `'in:'.implode(',', UserRole::values())`.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $r) => $r->value, self::cases());
    }

    /**
     * True for UNAB internal roles (Google OAuth flow). False for the
     * external evaluator credential flow.
     */
    public function isInternal(): bool
    {
        return $this !== self::EvaluadorExterno;
    }
}
