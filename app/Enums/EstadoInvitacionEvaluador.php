<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoInvitacionEvaluador: string
{
    case Pendiente = 'Pendiente';
    case Aceptada = 'Aceptada';
    case Rechazada = 'Rechazada';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
        };
    }
}
