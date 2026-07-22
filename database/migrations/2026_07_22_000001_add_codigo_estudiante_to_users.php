<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an optional institutional student code (e.g. U00167215)
     * to the users table. Populated by the coordinator when adding
     * students via the whitelist form, or editable later through
     * the user management modal.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('codigo_estudiante', 20)
                ->nullable()
                ->index()
                ->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['codigo_estudiante']);
            $table->dropColumn('codigo_estudiante');
        });
    }
};
