<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoFirma: string
{
    case Pendiente = 'Pendiente';
    case FirmadaEstudiante = 'FirmadaEstudiante';
    case FirmadaDirector = 'FirmadaDirector';
    case Completada = 'Completada';
    case Sospechosa = 'Sospechosa';
    case NoFirmada = 'NoFirmada';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::FirmadaEstudiante => 'Firmada por Estudiante',
            self::FirmadaDirector => 'Firmada por Director',
            self::Completada => 'Completada',
            self::Sospechosa => 'Sospechosa',
            self::NoFirmada => 'No Firmada',
        };
    }
}
