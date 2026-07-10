<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX authorized_emails_email_lower_index ON authorized_emails (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS authorized_emails_email_lower_index');
    }
};
