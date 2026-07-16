<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluador_proyecto', function (Blueprint $table): void {
            $table->date('fecha')->nullable()->after('assigned_at');
            $table->time('hora_inicio')->nullable()->after('fecha');
            $table->time('hora_fin')->nullable()->after('hora_inicio');
            $table->string('fase', 50)->nullable()->after('hora_fin');
        });
    }

    public function down(): void
    {
        Schema::table('evaluador_proyecto', function (Blueprint $table): void {
            $table->dropColumn(['fecha', 'hora_inicio', 'hora_fin', 'fase']);
        });
    }
};
