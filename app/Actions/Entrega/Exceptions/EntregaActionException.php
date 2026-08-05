<?php

declare(strict_types=1);

namespace App\Actions\Entrega\Exceptions;

use RuntimeException;

/**
 * Domain exception thrown by Entrega actions when a business rule rejects
 * the operation. The controller translates it back to the legacy
 * `{"error": "..."}` JSON shape with the original HTTP status.
 */
final class EntregaActionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
