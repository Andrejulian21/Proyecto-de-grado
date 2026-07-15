<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Coordinador (Google OAuth — via whitelist) ──
        AuthorizedEmail::create([
            'email' => 'mafanador856@unab.edu.co',
            'name' => 'Miguel Angel Afanador Quintero',
            'role' => UserRole::Coordinador,
        ]);

        User::create([
            'name' => 'Miguel Angel Afanador Quintero',
            'email' => 'mafanador856@unab.edu.co',
            'role' => UserRole::Coordinador,
            'es_externo' => false,
        ]);

        // ── 2. Estudiante (login externo con credenciales) ──
        User::create([
            'name' => 'Miguel Angel Estudiante',
            'email' => 'miguelafanquin10@gmail.com',
            'password' => Hash::make('Pruebas123!'),
            'password_changed_at' => now(),
            'role' => UserRole::Estudiante,
            'es_externo' => true,
        ]);

        // ── 3. Director (login externo con credenciales) ──
        User::create([
            'name' => 'Miguel Angel Director',
            'email' => 'miguelafanquin.director@gmail.com',
            'password' => Hash::make('Pruebas123!'),
            'password_changed_at' => now(),
            'role' => UserRole::Director,
            'es_externo' => true,
        ]);

        $this->command->info('Test users seeded: Coordinador (OAuth), Estudiante (externo), Director (externo)');
    }
}
