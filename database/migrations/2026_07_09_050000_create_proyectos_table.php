<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('title', 500);
            $table->unsignedBigInteger('director_id')->nullable();
            $table->unsignedBigInteger('semester_id');
            $table->string('current_phase', 50)->default('anteproyecto');
            $table->string('status', 50)->default('en_curso');
            $table->boolean('requires_group_justification')->default(false);
            $table->integer('alert_count')->default(0);
            $table->timestamps();

            $table->foreign('director_id')->references('id')->on('users');
            $table->foreign('semester_id')->references('id')->on('semestres');
            $table->index('director_id');
            $table->index('semester_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
