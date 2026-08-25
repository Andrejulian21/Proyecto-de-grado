<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (issue #39): guarantee the entrega_proyecto pivot covers 100% of
 * the entregas before the legacy `entregas.proyecto_id` column is dropped.
 *
 * Any entrega that still carries a direct proyecto_id but has no pivot row
 * gets its pivot completed. Idempotent: runs as a no-op when the data is
 * already clean (verified by the phase 2 query on :memory:).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('entregas', 'proyecto_id')) {
            return;
        }

        DB::table('entregas')
            ->whereNotNull('proyecto_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('entrega_proyecto')
                    ->whereColumn('entrega_proyecto.entrega_id', 'entregas.id');
            })
            ->orderBy('id')
            ->each(function ($entrega) {
                DB::table('entrega_proyecto')->insertOrIgnore([
                    'entrega_id' => $entrega->id,
                    'proyecto_id' => $entrega->proyecto_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Data migration: pivots are never deleted here.
    }
};
