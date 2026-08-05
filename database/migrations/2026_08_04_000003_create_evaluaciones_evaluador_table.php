<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_evaluador', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // RF-EVA-05: one evaluation per assignment. UNIQUE enforced at
            // the DB level so duplicate POST /evaluar requests are rejected
            // even if the controller check is bypassed.
            $table->unsignedBigInteger('evaluador_proyecto_id')->unique();
            $table->decimal('nota', 4, 2);
            $table->text('observaciones')->nullable();
            // RF-EVA-02/RF-EVA-03: timestamp the moment the evaluator
            // submitted the grade. Distinct from created_at so the audit
            // story stays explicit (when the row hit the DB vs. when the
            // user pressed "Enviar").
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->foreign('evaluador_proyecto_id')
                ->references('id')
                ->on('evaluador_proyecto')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_evaluador');
    }
};
