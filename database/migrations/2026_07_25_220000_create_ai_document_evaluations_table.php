<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_document_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('entrega_id')->constrained('entregas')->cascadeOnDelete();
            // Nullable: student pre-submission may use a temporary DOCX (no official VersionDocumento).
            $table->foreignId('version_documento_id')
                ->nullable()
                ->constrained('versiones_documento')
                ->nullOnDelete();
            $table->string('type', 64);
            $table->string('status', 32);
            $table->string('provider', 64)->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            $table->json('result_json')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['entrega_id', 'type', 'created_at']);
            $table->index(['version_documento_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_evaluations');
    }
};
