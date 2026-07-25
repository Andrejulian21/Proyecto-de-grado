<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrega_proyecto', function (Blueprint $table) {
            $table->string('estado', 50)->nullable()->after('proyecto_id');
            $table->text('observaciones_director')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('entrega_proyecto', function (Blueprint $table) {
            $table->dropColumn(['estado', 'observaciones_director']);
        });
    }
};
