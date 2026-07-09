<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proyecto_id');
            $table->string('phase', 50);
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('status', 50)->default('pendiente');
            $table->decimal('consolidated_grade', 5, 2)->nullable();
            $table->boolean('evaluation_complete')->default(false);
            $table->timestamps();

            $table->foreign('proyecto_id')->references('id')->on('proyectos')->onDelete('cascade');
            $table->index('proyecto_id');
            $table->index(['proyecto_id', 'phase']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
