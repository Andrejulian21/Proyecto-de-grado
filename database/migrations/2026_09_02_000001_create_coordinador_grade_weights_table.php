<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordinador_grade_weights', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('semestre_id');
            $table->string('tipo', 10);
            $table->decimal('peso_entregas', 5, 2)->default(40);
            $table->decimal('peso_evaluadores', 5, 2)->default(30);
            $table->decimal('peso_presentacion', 5, 2)->default(30);
            $table->timestamps();

            $table->foreign('semestre_id')->references('id')->on('semestres')->onDelete('cascade');
            $table->unique(['semestre_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinador_grade_weights');
    }
};
