<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluador_proyecto', function (Blueprint $table): void {
            // RF-EVA-05: indicates whether the external evaluator has already
            // submitted an EvaluacionEvaluador for this assignment. Defaults
            // to false (pending evaluation).
            $table->boolean('evaluado')->default(false)->after('fase');
        });
    }

    public function down(): void
    {
        Schema::table('evaluador_proyecto', function (Blueprint $table): void {
            $table->dropColumn('evaluado');
        });
    }
};
