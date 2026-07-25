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
            $table->text('evaluation_metrics')->nullable()->after('acceptance_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table): void {
            $table->dropColumn('evaluation_metrics');
        });
    }
};
