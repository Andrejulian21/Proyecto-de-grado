<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacoras', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proyecto_id');
            $table->string('topic', 500);
            $table->text('notes')->nullable();
            $table->string('evidence_file', 500)->nullable();
            $table->date('meeting_date');
            $table->string('signature_status', 50)->default('Pendiente');
            $table->timestamp('student_signed_at')->nullable();
            $table->timestamp('director_signed_at')->nullable();
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign('proyecto_id')->references('id')->on('proyectos')->onDelete('cascade');
            $table->index('proyecto_id');
            $table->index('signature_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
