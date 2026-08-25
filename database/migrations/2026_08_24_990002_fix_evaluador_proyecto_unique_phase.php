<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #51 — Defect 2: the legacy UNIQUE (proyecto_id, evaluador_id) was
 * defined before the `fase` column existed. Once `fase` was added the
 * constraint became too strict: assigning the SAME evaluator to the SAME
 * project in BOTH phases (presentacion_anteproyecto / presentacion_final)
 * is a legitimate use case and produced an uncaught 500.
 *
 * Fix: widen the uniqueness to (proyecto_id, evaluador_id, fase).
 *
 * NOTE: before applying to a non-empty database, confirm no existing row
 * already duplicates a (proyecto_id, evaluador_id, fase) tuple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluador_proyecto', function (Blueprint $table): void {
            $table->dropUnique(['proyecto_id', 'evaluador_id']);
            $table->unique(['proyecto_id', 'evaluador_id', 'fase'], 'evaluador_proyecto_proyecto_evaluador_fase_unique');
        });
    }

    public function down(): void
    {
        Schema::table('evaluador_proyecto', function (Blueprint $table): void {
            $table->dropUnique('evaluador_proyecto_proyecto_evaluador_fase_unique');
            $table->unique(['proyecto_id', 'evaluador_id']);
        });
    }
};
