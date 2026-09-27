<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anuncios', function (Blueprint $table): void {
            $table->foreignId('semestre_id')
                ->nullable()
                ->after('author_id')
                ->constrained('semestres')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('anuncios', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('semestre_id');
        });
    }
};
