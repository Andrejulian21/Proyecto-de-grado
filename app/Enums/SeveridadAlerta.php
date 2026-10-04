<?php

declare(strict_types=1);

namespace App\Enums;

enum SeveridadAlerta: string
{
    case Media = 'media';

    case Alta = 'alta';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }
}
