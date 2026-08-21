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
        $this->dropUniqueOnColumns('versiones_documento', ['entrega_id', 'version_number']);

        if (! $this->indexExists('versiones_documento', 'versiones_documento_ep_archivo_version_unique')) {
            Schema::table('versiones_documento', function (Blueprint $table) {
                $table->unique(
                    ['entrega_proyecto_id', 'archivo_requerido_id', 'version_number'],
                    'versiones_documento_ep_archivo_version_unique'
                );
            });
        }

        $this->backfillDocumentosSolicitados();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('versiones_documento', 'versiones_documento_ep_archivo_version_unique');

        if (! $this->hasUniqueOnColumns('versiones_documento', ['entrega_id', 'version_number'])) {
            Schema::table('versiones_documento', function (Blueprint $table) {
                $table->unique(['entrega_id', 'version_number']);
            });
        }
    }

    /**
     * Local SQLite (dump/legacy) may not have the unique created by the original
     * migration; tests on :memory: do. Drop only when the index is present.
     *
     * @param  array<int, string>  $columns
     */
    private function dropUniqueOnColumns(string $table, array $columns): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (! ($index['unique'] ?? false)) {
                continue;
            }

            if (($index['columns'] ?? []) !== $columns) {
                continue;
            }

            $this->dropIndexIfExists($table, $index['name']);
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasUniqueOnColumns(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropUnique($indexName);
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
