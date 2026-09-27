<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_document_evaluations', function (Blueprint $table) {
            $table->string('model', 64)->nullable()->after('provider');
            $table->boolean('was_truncated')->default(false)->after('prompt_version');
            $table->unsignedInteger('original_chars')->nullable()->after('was_truncated');
            $table->unsignedInteger('kept_chars')->nullable()->after('original_chars');
        });
    }

    public function down(): void
    {
        Schema::table('ai_document_evaluations', function (Blueprint $table) {
            $table->dropColumn(['model', 'was_truncated', 'original_chars', 'kept_chars']);
        });
    }
};
