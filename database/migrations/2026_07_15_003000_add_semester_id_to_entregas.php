<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->constrained('semestres')->nullOnDelete()->after('id');
        });

        // Make proyecto_id nullable in PostgreSQL (SQLite handles it via the original migration change)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE entregas ALTER COLUMN proyecto_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign(['semester_id']);
            $table->dropColumn('semester_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE entregas ALTER COLUMN proyecto_id SET NOT NULL');
        }
    }
};
