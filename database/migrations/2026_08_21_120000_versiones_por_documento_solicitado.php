<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_documento', function (Blueprint $table) {
            $table->dropUnique(['entrega_id', 'version_number']);
        });

        Schema::table('versiones_documento', function (Blueprint $table) {
            $table->unique(
                ['entrega_proyecto_id', 'archivo_requerido_id', 'version_number'],
                'versiones_documento_ep_archivo_version_unique'
            );
        });

        $this->backfillDocumentosSolicitados();
    }

    public function down(): void
    {
        Schema::table('versiones_documento', function (Blueprint $table) {
            $table->dropUnique('versiones_documento_ep_archivo_version_unique');
        });

        Schema::table('versiones_documento', function (Blueprint $table) {
            $table->unique(['entrega_id', 'version_number']);
        });
    }

    /**
     * Preserve existing deliveries: empty JSON becomes one titled document;
     * versions without archivo_requerido_id are attributed to that document.
     */
    private function backfillDocumentosSolicitados(): void
    {
        $entregas = DB::table('entregas')->select('id', 'title', 'archivos_requeridos')->get();

        foreach ($entregas as $entrega) {
            $docs = json_decode((string) ($entrega->archivos_requeridos ?? 'null'), true);

            if (! is_array($docs) || $docs === []) {
                $docs = [[
                    'slug' => 'documento',
                    'nombre' => filled($entrega->title) ? (string) $entrega->title : 'Documento',
                    'versionamiento' => true,
                    'analizable_ia' => false,
                ]];

                DB::table('entregas')->where('id', $entrega->id)->update([
                    'archivos_requeridos' => json_encode($docs),
                ]);
            }

            $firstSlug = $docs[0]['slug'] ?? $docs[0]['id'] ?? 'documento';

            DB::table('versiones_documento')
                ->where('entrega_id', $entrega->id)
                ->whereNull('archivo_requerido_id')
                ->update(['archivo_requerido_id' => $firstSlug]);
        }
    }
};
