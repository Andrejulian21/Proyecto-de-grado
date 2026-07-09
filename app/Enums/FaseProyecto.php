<?php

declare(strict_types=1);

namespace App\Enums;

enum FaseProyecto: string
{
    case Anteproyecto = 'anteproyecto';
    case PresentacionAnteproyecto = 'presentacion_anteproyecto';
    case Desarrollo = 'desarrollo';
    case PresentacionFinal = 'presentacion_final';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Anteproyecto => 'Anteproyecto',
            self::PresentacionAnteproyecto => 'Presentación Anteproyecto',
            self::Desarrollo => 'Desarrollo',
            self::PresentacionFinal => 'Presentación Final',
        };
    }

    /**
     * Return the next phase, or null if this is the last phase.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Anteproyecto => self::PresentacionAnteproyecto,
            self::PresentacionAnteproyecto => self::Desarrollo,
            self::Desarrollo => self::PresentacionFinal,
            self::PresentacionFinal => null,
        };
    }
}
