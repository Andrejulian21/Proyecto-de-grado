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
        // Este usuario se loguea con Google @unab.edu.co.
        // La whitelist permite que al hacer OAuth se le asigne rol Coordinador.
        AuthorizedEmail::create([
            'email' => 'jarteaga145@unab.edu.co',
            'name' => 'Juan Arteaga',
            'role' => UserRole::Coordinador,
        ]);

        // También lo creamos en users para que pueda hacer login directo
        // si ya pasó por el flujo de OAuth antes.
        User::create([
            'name' => 'Nicolas Moreno',
            'email' => 'nmoreno534@unab.edu.co',
            'role' => UserRole::Coordinador,
            'es_externo' => false,
        ]);

        AuthorizedEmail::create([
            'email' => 'nmoreno534@unab.edu.co',
            'name' => 'Nicolas Moreno',
            'role' => UserRole::Coordinador,
        ]);

        // También lo creamos en users para que pueda hacer login directo
        // si ya pasó por el flujo de OAuth antes.
        User::create([
            'name' => 'Juan Arteaga',
            'email' => 'jarteaga145@unab.edu.co',
            'role' => UserRole::Coordinador,
            'es_externo' => false,
        ]);

        

        // ── 2. Estudiante (login externo con credenciales) ──
        User::create([
            'name' => 'Julian Estudiante',
            'email' => 'juliarteaga938@gmail.com',
            'password' => Hash::make('Pruebas123!'),
            'role' => UserRole::Estudiante,
            'es_externo' => true,
        ]);

        // ── 3. Director (login externo con credenciales) ──
        User::create([
            'name' => 'Julian Director',
            'email' => 'julian21arteaga@gmail.com',
            'password' => Hash::make('Pruebas123!'),
            'role' => UserRole::Director,
            'es_externo' => true,
        ]);

        $this->command->info('Test users seeded: Coordinador (OAuth), Estudiante (externo), Director (externo)');
    }
}
