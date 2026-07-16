<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite requires recreating the table for nullable changes.
        // We use raw SQLite to alter the column.
        Schema::table('entregas', function (Blueprint $table) {
            $table->unsignedBigInteger('proyecto_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->unsignedBigInteger('proyecto_id')->nullable(false)->change();
        });
    }
};
