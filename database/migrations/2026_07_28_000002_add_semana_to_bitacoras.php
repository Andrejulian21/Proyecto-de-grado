<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 4 — Seguimiento y Firma: add `semana` to bitacoras.
     *
     * RF-WK-01: `semana` is unsigned tiny integer, nullable, with a unique
     * constraint on (proyecto_id, semana). Nullable to make the migration
     * safe on tables that already have rows; backfill below populates them.
     *
     * RF-WK-02: existing rows are backfilled with ROW_NUMBER() partitioned
     * by proyecto_id, ordered by created_at (and id as a deterministic
     * tie-breaker for rows that share a timestamp). Each existing logbook
     * gets 1, 2, 3, ... in creation order, so the constraint is satisfied
     * after the backfill completes.
     */
    public function up(): void
    {
        Schema::table('bitacoras', function (Blueprint $table): void {
            $table->unsignedTinyInteger('semana')
                ->nullable()
                ->after('meeting_date');
            $table->unique(['proyecto_id', 'semana'], 'bitacoras_proyecto_id_semana_unique');
        });

        // Backfill: assign semana by creation order per proyecto. Skips rows
        // already populated so re-running the migration is a no-op. Uses a
        // correlated subquery instead of UPDATE ... FROM so the same SQL
        // works on both PostgreSQL (production) and SQLite (in-memory tests).
        DB::statement(<<<'SQL'
            WITH numbered AS (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY proyecto_id
                           ORDER BY created_at ASC, id ASC
                       ) AS rn
                FROM bitacoras
                WHERE semana IS NULL
            )
            UPDATE bitacoras
            SET semana = (
                SELECT rn FROM numbered WHERE numbered.id = bitacoras.id
            )
            WHERE id IN (SELECT id FROM numbered)
              AND semana IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('bitacoras', function (Blueprint $table): void {
            $table->dropUnique('bitacoras_proyecto_id_semana_unique');
            $table->dropColumn('semana');
        });
    }
};
