<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Immutable, append-only audit trail (T-010). Every auth event
     * (login.success, login.rejected, role.changed, etc.) lands here via
     * the AuditEvent → WriteAuditLog listener pipeline.
     *
     * Columns:
     *   - id, user_id (FK users.id, nullable — system actions have no user)
     *   - action (string 64, e.g. "login.success")
     *   - description (text, nullable, free-form context)
     *   - ip_address (string 45 — IPv6 compatible)
     *   - user_agent (text, nullable)
     *   - metadata (json, nullable — extra structured context)
     *   - created_at only — NO updated_at, NO soft deletes
     *
     * Immutability is enforced two ways:
     *   1. No `updated_at` column, no soft deletes (structural)
     *   2. The application DB role MUST NOT have UPDATE/DELETE privilege
     *      on this table (see deployment runbook). Direct DB writes from
     *      the app are limited to INSERT via the WriteAuditLog listener.
     *
     * Indexes: user_id, action, created_at (composite index on
     * (action, created_at) will be added in T-024 for the coordinator
     * filter view).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
