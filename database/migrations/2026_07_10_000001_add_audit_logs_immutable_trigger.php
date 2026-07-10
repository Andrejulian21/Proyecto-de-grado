<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #12 — DB-level immutability for `audit_logs`.
 *
 * Installs a PostgreSQL `BEFORE UPDATE OR DELETE` trigger on the
 * `audit_logs` table that raises an exception on any mutation. The
 * audit trail is therefore append-only at the database level, not
 * only at the application level (AuditLog model + builder guards).
 *
 * Privileged maintenance (e.g. the daily `audit:archive` command)
 * bypasses the trigger by issuing `SET LOCAL session_replication_role
 * = replica` inside a transaction. See `app/Console/Commands/AuditArchive.php`.
 *
 * The migration is a no-op on SQLite — the trigger cannot exist
 * outside PostgreSQL. The Feature test that exercises this contract
 * (`tests/Feature/Admin/AuditLogImmutabilityTest.php`) is skipped on
 * SQLite and run against the real PostgreSQL service in CI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only; UPDATE/DELETE is not allowed'
                    USING ERRCODE = 'P0001';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON audit_logs;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_audit_logs_immutable
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON audit_logs;
        SQL);

        DB::statement(<<<'SQL'
            DROP FUNCTION IF EXISTS audit_logs_prevent_mutation();
        SQL);
    }
};
