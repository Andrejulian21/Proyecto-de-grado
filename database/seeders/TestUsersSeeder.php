<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('TestUsersSeeder omitido: el entorno no es local ni testing.');
            return;
        }

        // ── 1. Coordinadores (Google OAuth — via whitelist) ─────────────
        AuthorizedEmail::updateOrCreate(
            ['email' => 'jarteaga145@unab.edu.co'],
            ['name' => 'Juan Arteaga', 'role' => UserRole::Coordinador],
        );

        User::updateOrCreate(
            ['email' => 'nmoreno534@unab.edu.co'],
            ['name' => 'Nicolas Moreno', 'role' => UserRole::Coordinador, 'es_externo' => false],
        );

        AuthorizedEmail::updateOrCreate(
            ['email' => 'nmoreno534@unab.edu.co'],
            ['name' => 'Nicolas Moreno', 'role' => UserRole::Coordinador],
        );

        User::updateOrCreate(
            ['email' => 'jarteaga145@unab.edu.co'],
            ['name' => 'Juan Arteaga', 'role' => UserRole::Coordinador, 'es_externo' => false],
        );

        // ── 2. Estudiantes (login externo con credenciales) ─────────────

        $estudianteJulian = User::updateOrCreate(
            ['email' => 'juliarteaga938@gmail.com'],
            [
                'name'       => 'Julian Estudiante',
                'password'   => Hash::make('Pruebas123!'),
                'role'       => UserRole::Estudiante,
                'es_externo' => true,
            ],
        );

        $estudianteNicolas = User::updateOrCreate(
            ['email' => 'nicorfire1.4@gmail.com'],
            [
                'name'                => 'Nicolas Estudiante Test',
                'password'            => Hash::make('Pruebas123!'),
                'role'                => UserRole::Estudiante,
                'es_externo'          => true,
                'password_changed_at' => now(),
            ],
        );

        // ── 3. Director (login externo con credenciales) ────────────────

        $directorJulian = User::updateOrCreate(
            ['email' => 'julian21arteaga@gmail.com'],
            [
                'name'       => 'Julian Director',
                'password'   => Hash::make('Pruebas123!'),
                'role'       => UserRole::Director,
                'es_externo' => true,
            ],
        );

        // ── 4. Semestre activo ──────────────────────────────────────────

        $semestre = Semestre::firstOrCreate(
            ['name' => '2026-1'],
            [
                'start_date' => '2026-02-01',
                'end_date'   => '2026-06-30',
                'is_active'  => true,
            ],
        );

        // ── 5. Proyecto de prueba para Nicolas Estudiante ───────────────

        $proyecto = Proyecto::updateOrCreate(
            ['title' => 'Sistema de Gestion de Proyectos de Grado', 'semester_id' => $semestre->id],
            [
                'director_id'                 => $directorJulian->id,
                'current_phase'               => FaseProyecto::Desarrollo,
                'status'                      => EstadoProyecto::EnCurso,
                'requires_group_justification' => false,
            ],
        );

        // Vincular estudiante al proyecto (ignorar si ya existe)
        DB::table('proyecto_estudiante')->insertOrIgnore([
            'proyecto_id' => $proyecto->id,
            'user_id'     => $estudianteNicolas->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->command->info(sprintf(
            'Test users seeded: 2 coordinadores, 2 estudiantes, 1 director, 1 proyecto (%s)',
            $proyecto->code,
        ));
    }
}
