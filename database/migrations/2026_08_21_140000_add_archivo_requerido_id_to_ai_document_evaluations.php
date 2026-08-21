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
        if (! Schema::hasTable('ai_document_evaluations')) {
            return;
        }

        if (! Schema::hasColumn('ai_document_evaluations', 'archivo_requerido_id')) {
            Schema::table('ai_document_evaluations', function (Blueprint $table) {
                $table->string('archivo_requerido_id')->nullable();
                $table->index(
                    ['entrega_id', 'archivo_requerido_id', 'version_documento_id'],
                    'ai_eval_entrega_documento_version_index',
                );
            });
        }

        $this->backfillDocumentoId();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_document_evaluations')) {
            return;
        }

        if (Schema::hasColumn('ai_document_evaluations', 'archivo_requerido_id')) {
            Schema::table('ai_document_evaluations', function (Blueprint $table) {
                $table->dropIndex('ai_eval_entrega_documento_version_index');
                $table->dropColumn('archivo_requerido_id');
            });
        }
    }

    /**
     * Fill documento identity from the version when present; otherwise from the
     * entrega's current AI-analyzable document. Never invent a version_id.
     */
    private function backfillDocumentoId(): void
    {
        $rows = DB::table('ai_document_evaluations')
            ->whereNull('archivo_requerido_id')
            ->select('id', 'entrega_id', 'version_documento_id')
            ->get();

        foreach ($rows as $row) {
            $documentoId = null;

            if ($row->version_documento_id) {
                $documentoId = DB::table('versiones_documento')
                    ->where('id', $row->version_documento_id)
                    ->value('archivo_requerido_id');
            }

            if (! is_string($documentoId) || $documentoId === '') {
                $documentoId = $this->idDocumentoAnalizableDeEntrega((int) $row->entrega_id);
            }

            if (! is_string($documentoId) || $documentoId === '') {
                continue;
            }

            DB::table('ai_document_evaluations')->where('id', $row->id)->update([
                'archivo_requerido_id' => $documentoId,
            ]);
        }
    }

    private function idDocumentoAnalizableDeEntrega(int $entregaId): ?string
    {
        $json = DB::table('entregas')->where('id', $entregaId)->value('archivos_requeridos');
        $documentos = json_decode((string) ($json ?? 'null'), true);

        if (! is_array($documentos)) {
            return null;
        }

        foreach ($documentos as $documento) {
            if (! is_array($documento) || ! filter_var($documento['analizable_ia'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $id = $documento['slug'] ?? $documento['id'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        return null;
    }
};
