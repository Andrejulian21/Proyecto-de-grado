<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_estudiante', function (Blueprint $table): void {
            $table->unsignedBigInteger('proyecto_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('proyecto_id')->references('id')->on('proyectos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['proyecto_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_estudiante');
    }
};
