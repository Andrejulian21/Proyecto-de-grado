<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (issue #39): remove the legacy `entregas.proyecto_id` column.
 *
 * The entrega_proyecto pivot is the single source of truth for the
 * entrega-project link. Once this column is gone, any code that tries to
 * use it fails visibly instead of silently returning null.
 *
 * IRREVERSIBLE as to data: requires a DB backup and explicit team
 * confirmation before running on any non-local environment. The phase 2
 * verification query must return 0 first:
 *
 *   SELECT COUNT(*) FROM entregas
 *   WHERE proyecto_id IS NOT NULL
 *     AND id NOT IN (SELECT entrega_id FROM entrega_proyecto);
 *
 * The 2026_08_24_000001 backfill migration guarantees that result.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('entregas', 'proyecto_id')) {
            return;
        }

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign(['proyecto_id']);
            $table->dropIndex(['proyecto_id', 'phase']);
            $table->dropIndex(['proyecto_id']);
            $table->dropColumn('proyecto_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('entregas', 'proyecto_id')) {
            return;
        }

        Schema::table('entregas', function (Blueprint $table) {
            $table->unsignedBigInteger('proyecto_id')->nullable();
            $table->foreign('proyecto_id')->references('id')->on('proyectos')->onDelete('cascade');
            $table->index('proyecto_id');
            $table->index(['proyecto_id', 'phase']);
        });
    }
};
