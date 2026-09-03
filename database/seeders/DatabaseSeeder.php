<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Coordinadores autorizados (Google OAuth whitelist)
        AuthorizedEmail::updateOrCreate(
            ['email' => 'jarteaga145@unab.edu.co'],
            ['name' => 'Julian Arteaga', 'role' => UserRole::Coordinador],
        );

        AuthorizedEmail::updateOrCreate(
            ['email' => 'lpardo688@unab.edu.co'],
            ['name' => 'Luisa Parra', 'role' => UserRole::Coordinador],
        );
    }
}
