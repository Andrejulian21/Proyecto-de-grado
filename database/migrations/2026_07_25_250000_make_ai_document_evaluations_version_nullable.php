<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For environments that already ran the original non-nullable FK.
 * Fresh installs get nullable from 2026_07_25_220000 create migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_document_evaluations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('ai_document_evaluations', function (Blueprint $table) {
            $table->dropForeign(['version_documento_id']);
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ai_document_evaluations ALTER COLUMN version_documento_id DROP NOT NULL');
        } else {
            DB::statement('ALTER TABLE ai_document_evaluations MODIFY version_documento_id BIGINT UNSIGNED NULL');
        }

        Schema::table('ai_document_evaluations', function (Blueprint $table) {
            $table->foreign('version_documento_id')
                ->references('id')
                ->on('versiones_documento')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Leave nullable to avoid failing when temp evaluations exist.
    }
};
