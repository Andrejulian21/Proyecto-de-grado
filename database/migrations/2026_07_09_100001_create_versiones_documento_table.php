<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_documento', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entrega_id');
            $table->integer('version_number');
            $table->string('file_path', 500);
            $table->integer('file_size')->nullable();
            $table->string('original_name', 255);
            $table->text('director_notes')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('entrega_id')->references('id')->on('entregas')->onDelete('cascade');
            $table->unique(['entrega_id', 'version_number']);
            $table->index('entrega_id');
            $table->index(['entrega_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones_documento');
    }
};
