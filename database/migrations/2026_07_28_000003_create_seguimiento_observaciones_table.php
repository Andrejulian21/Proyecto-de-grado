<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 2 — Backend de Seguimiento: create seguimiento_observaciones table.
     *
     * Stores coordinator observations per (project, semester, phase) tuple.
     * The unique constraint ensures at most one observation per cell.
     */
    public function up(): void
    {
        Schema::create('seguimiento_observaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained()->cascadeOnDelete();
            $table->string('fase', 50);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['proyecto_id', 'semestre_id', 'fase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_observaciones');
    }
};
