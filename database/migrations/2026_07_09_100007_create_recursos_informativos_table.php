<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recursos_informativos', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_id');
            $table->string('title', 500);
            $table->string('category', 100);
            $table->text('description')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('link', 500)->nullable();
            $table->integer('access_count')->default(0);
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos_informativos');
    }
};
