<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Lockout policy for the external evaluator credential login (T-016).
 *
 * Rules (from `spec.md`):
 *   - 3 wrong attempts within 10 minutes → account is locked.
 *   - 5th failed attempt within lockout window → extends lockout 15 min.
 *
 * Default values match the spec exactly. Override via env or constructor
 * injection in tests.
 */
class LoginAttemptPolicy
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $windowMinutes = 10,
        private readonly int $lockMinutes = 15,
    ) {}

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function windowMinutes(): int
    {
        return $this->windowMinutes;
    }

    public function lockMinutes(): int
    {
        return $this->lockMinutes;
    }
}
