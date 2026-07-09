<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entrega_id');
            $table->unsignedBigInteger('evaluador_id');
            $table->string('criterio', 255);
            $table->decimal('percentage', 5, 2);
            $table->decimal('grade', 4, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->foreign('entrega_id')->references('id')->on('entregas')->onDelete('cascade');
            $table->foreign('evaluador_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['entrega_id', 'evaluador_id', 'criterio']);
            $table->index('entrega_id');
            $table->index('evaluador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
