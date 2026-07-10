<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX users_email_lower_index ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_index');
    }
};
