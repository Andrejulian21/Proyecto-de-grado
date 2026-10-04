<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoAlerta: string
{
    /** R1 — a bitácora went unsigned for more than an hour. */
    case BitacoraSinFirmar = 'bitacora_sin_firmar';

    /** R2 — a delivery's window closed with nothing uploaded by the project. */
    case EntregaVencida = 'entrega_vencida';

    /** R3 — a director logged several signatures inside a short window. */
    case FirmasSospechosas = 'firmas_sospechosas';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::BitacoraSinFirmar => 'Bitácora sin firmar',
            self::EntregaVencida => 'Entrega vencida',
            self::FirmasSospechosas => 'Firmas sospechosas',
        };
    }
}
