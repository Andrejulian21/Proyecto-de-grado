<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluador_proyecto', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proyecto_id');
            $table->unsignedBigInteger('evaluador_id');
            $table->string('invitation_status', 50)->default('Pendiente');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('proyecto_id')->references('id')->on('proyectos')->onDelete('cascade');
            $table->foreign('evaluador_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['proyecto_id', 'evaluador_id']);
            $table->index('proyecto_id');
            $table->index('evaluador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluador_proyecto');
    }
};
