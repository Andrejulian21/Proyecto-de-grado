<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoProyecto: string
{
    case EnCurso = 'en_curso';
    case EnRiesgo = 'en_riesgo';
    case Incumplimiento = 'incumplimiento';
    case Completado = 'completado';

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
            self::EnCurso => 'En Curso',
            self::EnRiesgo => 'En Riesgo',
            self::Incumplimiento => 'Incumplimiento',
            self::Completado => 'Completado',
        };
    }
}
