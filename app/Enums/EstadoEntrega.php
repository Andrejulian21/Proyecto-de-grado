<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoEntrega: string
{
    case Creada = 'creacion';
    case Solicitada = 'solicitada';
    case Pendiente = 'pendiente';
    case Enviada = 'enviada';
    case Revisada = 'revisada';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Creada => 'Creada',
            self::Solicitada => 'Solicitada',
            self::Pendiente => 'Pendiente',
            self::Enviada => 'Enviada',
            self::Revisada => 'Revisada',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
        };
    }
}
