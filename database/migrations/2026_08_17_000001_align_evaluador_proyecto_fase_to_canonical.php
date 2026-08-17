<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea `evaluador_proyecto.fase` al dominio canónico de FaseProyecto.
 *
 * El módulo de asignación de evaluadores usaba los valores legacy
 * 'Anteproyecto'/'Final', mientras que el enum canónico
 * (app/Enums/FaseProyecto.php) es 'presentacion_anteproyecto' /
 * 'presentacion_final'. Esta migración convierte los valores legacy ya
 * persistidos al dominio canónico (y down() lo revierte).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('evaluador_proyecto')
            ->where('fase', 'Anteproyecto')
            ->update(['fase' => 'presentacion_anteproyecto']);

        DB::table('evaluador_proyecto')
            ->where('fase', 'Final')
            ->update(['fase' => 'presentacion_final']);
    }

    public function down(): void
    {
        DB::table('evaluador_proyecto')
            ->where('fase', 'presentacion_anteproyecto')
            ->update(['fase' => 'Anteproyecto']);

        DB::table('evaluador_proyecto')
            ->where('fase', 'presentacion_final')
            ->update(['fase' => 'Final']);
    }
};
