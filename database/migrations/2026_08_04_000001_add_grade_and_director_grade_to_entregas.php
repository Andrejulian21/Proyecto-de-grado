<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table): void {
            // RF-ENT-03: weight percentage for the pair-sum rule (5,2 to allow 100.00 with margin).
            $table->decimal('grade_percentage', 5, 2)->nullable()->after('evaluation_complete');
            // RF-NOT-01: director grade assigned on review (4,2 → 0.00 - 5.00, escala 0-5).
            $table->decimal('director_grade', 4, 2)->nullable()->after('grade_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table): void {
            $table->dropColumn(['grade_percentage', 'director_grade']);
        });
    }
};
