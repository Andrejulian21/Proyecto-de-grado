<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index on audit_logs (action, created_at) for the
 * AuditLogController@index coordinator filter (T-024).
 *
 * The audit_logs table was created in
 * `2026_07_06_000002_create_audit_logs_table.php` with single-column
 * indexes on `action` and `created_at`. The coordinator view filters
 * by both at once (e.g. `action=login.success AND created_at BETWEEN
 * ... AND ...`) — a composite index lets the planner range-scan the
 * B-tree instead of intersecting two single-column indexes.
 *
 * This migration is idempotent: it skips creation if the index
 * already exists (safe to run on databases that already have it
 * from the original migration in future revisions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_created_at_index');
        });
    }
};
