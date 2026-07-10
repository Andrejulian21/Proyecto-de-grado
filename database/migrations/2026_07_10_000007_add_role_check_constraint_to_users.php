<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // CHECK constraint only applies to PostgreSQL
        }

        $validRoles = implode("', '", array_map(fn (UserRole $r) => $r->value, UserRole::cases()));
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('{$validRoles}'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
    }
};
