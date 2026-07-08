<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Coordinator-managed whitelist of emails allowed to log in via
     * Google OAuth. Each row gates one institutional user (T-009).
     *
     * Columns:
     *   - id, email (unique), role (UserRole string)
     *   - created_by (FK users.id, nullable — system-seeded rows have no creator)
     *   - timestamps
     */
    public function up(): void
    {
        Schema::create('authorized_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('role', 32)->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorized_emails');
    }
};
