<?php

declare(strict_types=1);

namespace App\Actions;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Genera un DOCX a partir de un template Word reemplazando los
 * placeholders `${clave}` (RF-CA-02/03, design D3).
 *
 * - Template faltante o ilegible → RuntimeException con mensaje claro en
 *   español (el controller responde 500 con ese mensaje).
 * - Devuelve la ruta del archivo temporal generado; el controller lo
 *   entrega como descarga y lo limpia.
 */
final class GenerateCartAction
{
    public const MENSAJE_SIN_TEMPLATE = 'La plantilla de carta no está disponible. Contacte al administrador.';

    /**
     * @param  array<string, string>  $placeholders
     */
    public function handle(string $templatePath, array $placeholders): string
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException(self::MENSAJE_SIN_TEMPLATE);
        }

        $processor = new TemplateProcessor($templatePath);

        foreach ($placeholders as $clave => $valor) {
            $processor->setValue((string) $clave, (string) ($valor ?? ''));
        }

        $destino = sys_get_temp_dir().'/carta_aval_'.bin2hex(random_bytes(8)).'.docx';
        $processor->saveAs($destino);

        return $destino;
    }
}
