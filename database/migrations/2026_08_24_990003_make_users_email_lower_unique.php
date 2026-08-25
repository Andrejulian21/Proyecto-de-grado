<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #51 — Defect 4: `users.email` is UNIQUE case-sensitively, and the
 * existing `users_email_lower_index` on `lower(email)` is non-unique (it
 * only accelerates lookups). As a result two accounts could differ only by
 * email case, and an external evaluator whose account was created with
 * uppercase letters could not log in typing their email in lowercase.
 *
 * Fix: replace the non-unique lower(email) index with a UNIQUE lower(email)
 * index so case-insensitive uniqueness is enforced at the DB level. The
 * index keeps the original name (`users_email_lower_index`) so existing
 * schema assertions remain valid; only its uniqueness changes.
 *
 * Works on both PostgreSQL and SQLite (expression indexes are supported on
 * SQLite >= 3.9). The User model also normalizes emails on write and
 * loginExterno() normalizes on read as defense-in-depth (see User.php).
 *
 * NOTE: before applying to a non-empty database, confirm there are no two
 * existing users whose emails differ only by case:
 *   SELECT email FROM users GROUP BY lower(email) HAVING COUNT(*) > 1;
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_index');
        DB::statement('CREATE UNIQUE INDEX users_email_lower_index ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_index');
        DB::statement('CREATE INDEX users_email_lower_index ON users (lower(email))');
    }
};
