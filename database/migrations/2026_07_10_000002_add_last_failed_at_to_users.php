<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issue #13 — sliding window for login lockout.
     *
     * `last_failed_at` anchors the 10-minute sliding window. Without
     * a timestamp, the lockout counter is cumulative across days
     * (yesterday's 3 fails lock the account today). The User model
     * now resets the counter to 0 when the most-recent failure is
     * older than `LoginAttemptPolicy::windowMinutes()`.
     *
     * The column is nullable because legitimate users (no failed
     * logins) and freshly-created accounts have no failure to record.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_failed_at')
                ->nullable()
                ->after('locked_until');

            $table->index('last_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_failed_at']);
            $table->dropColumn('last_failed_at');
        });
    }
};
