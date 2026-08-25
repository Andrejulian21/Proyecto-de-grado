<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #51 — Defect 1: enforce grade/percentage ranges at the database
 * level so every write path (controllers, seeders, data migrations) is
 * guarded, not only the EntregaController validation.
 *
 * Constraint set (per the audit):
 *   - evaluaciones.percentage            -> [0, 100]
 *   - evaluaciones.grade                 -> NULL or [0, 5]
 *   - evaluaciones_evaluador.nota        -> [0, 5]
 *   - entrega_proyecto.director_grade    -> NULL or [0, 5]
 *
 * PostgreSQL-only, mirroring the existing `users_role_check` migration
 * (`2026_07_10_000007_add_role_check_constraint_to_users.php`) so the
 * in-memory SQLite test database is not affected.
 *
 * NOTE: before applying to any non-empty database, confirm no existing row
 * violates the new constraints (see issue #51 "Verificación previa obligatoria").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // CHECK constraints only apply to PostgreSQL
        }

        DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT evaluaciones_percentage_range CHECK (percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT evaluaciones_grade_range CHECK (grade IS NULL OR grade BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE evaluaciones_evaluador ADD CONSTRAINT evaluaciones_evaluador_nota_range CHECK (nota BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE entrega_proyecto ADD CONSTRAINT entrega_proyecto_director_grade_range CHECK (director_grade IS NULL OR director_grade BETWEEN 0 AND 5)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS evaluaciones_percentage_range');
        DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS evaluaciones_grade_range');
        DB::statement('ALTER TABLE evaluaciones_evaluador DROP CONSTRAINT IF EXISTS evaluaciones_evaluador_nota_range');
        DB::statement('ALTER TABLE entrega_proyecto DROP CONSTRAINT IF EXISTS entrega_proyecto_director_grade_range');
    }
};
