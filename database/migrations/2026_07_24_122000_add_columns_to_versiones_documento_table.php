<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_documento', function (Blueprint $table) {
            $table->unsignedBigInteger('entrega_proyecto_id')->nullable()->after('entrega_id');
            $table->string('archivo_requerido_id', 50)->nullable()->after('entrega_proyecto_id');
            $table->boolean('descontinuado')->default(false)->after('archivo_requerido_id');

            $table->foreign('entrega_proyecto_id')
                ->references('id')
                ->on('entrega_proyecto')
                ->onDelete('set null');

            $table->index(['entrega_proyecto_id', 'archivo_requerido_id'], 'idx_versiones_ep_ar');
        });
    }

    public function down(): void
    {
        Schema::table('versiones_documento', function (Blueprint $table) {
            $table->dropForeign(['entrega_proyecto_id']);
            $table->dropIndex('idx_versiones_ep_ar');
            $table->dropColumn(['entrega_proyecto_id', 'archivo_requerido_id', 'descontinuado']);
        });
    }
};
