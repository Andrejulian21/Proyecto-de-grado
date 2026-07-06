<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add external-evaluator auth columns to the `users` table (T-016, T-017).
 *
 *   - `password_changed_at` (nullable timestamp) — when null on login,
 *     the session is flagged `must_change_password=true` and the user
 *     is forced to set a new password before any other route works.
 *   - `failed_attempts` (unsigned small int) — counter of consecutive
 *     failed credential logins. Resets on success.
 *   - `locked_until` (nullable timestamp) — while in the future, login
 *     returns 423 Locked.
 *
 * Index on `locked_until` so the lockout check (WHERE locked_until > now)
 * is O(log n) as the user table grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->unsignedSmallInteger('failed_attempts')->default(0)->after('password_changed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_attempts');

            $table->index('locked_until', 'users_locked_until_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_locked_until_index');
            $table->dropColumn(['password_changed_at', 'failed_attempts', 'locked_until']);
        });
    }
};
