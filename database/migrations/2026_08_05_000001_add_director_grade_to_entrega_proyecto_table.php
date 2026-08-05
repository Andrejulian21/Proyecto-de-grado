<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D3-rev (2026-08-05): the director's grade belongs to the STUDENT delivery
 * (per project), not to the general delivery template. It lives on
 * `entrega_proyecto` next to `observaciones_director`. The legacy
 * `entregas.director_grade` column is kept (unused) — a destructive
 * migration is not justified at this point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrega_proyecto', function (Blueprint $table) {
            $table->decimal('director_grade', 4, 2)->nullable()->after('observaciones_director');
        });
    }

    public function down(): void
    {
        Schema::table('entrega_proyecto', function (Blueprint $table) {
            $table->dropColumn('director_grade');
        });
    }
};
