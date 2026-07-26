<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoInvitacionEvaluador;
use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\DirectorAcademicProfile;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
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

        User::updateOrCreate(
            ['email' => 'mafanador856@unab.edu.co'],
            ['name' => 'Miguel Afanador', 'role' => UserRole::Coordinador, 'es_externo' => false],
        );

        AuthorizedEmail::updateOrCreate(
            ['email' => 'mafanador856@unab.edu.co'],
            ['name' => 'Miguel Afanador', 'role' => UserRole::Coordinador],
        );


    
        // ── 2. Estudiantes (login externo con credenciales) ─────────────

        $estudianteJulian = User::updateOrCreate(
            ['email' => 'juliarteaga938@gmail.com'],
            [
                'name'       => 'Julian Estudiante',
                // Plain text — User model casts password as 'hashed'.
                'password'   => 'Pruebas123!',
                'role'       => UserRole::Estudiante,
                'es_externo' => true,
            ],
        );

        $estudianteNicolas = User::updateOrCreate(
            ['email' => 'nicorfire1.4@gmail.com'],
            [
                'name'                => 'Nicolas Estudiante Test',
                'password'            => 'Pruebas123!',
                'role'                => UserRole::Estudiante,
                'es_externo'          => true,
                'password_changed_at' => now(),
            ],
        );

        // ── 3. Director (login externo con credenciales) ────────────────

        $directorJulian = User::updateOrCreate(
            ['email' => 'julian21arteaga@gmail.com'],
            [
                'name'         => 'Julian Director',
                'password'     => 'Pruebas123!',
                'role'         => UserRole::Director,
                'es_externo'   => true,
                'max_capacity' => 3,
                'areas'        => "Inteligencia Artificial\nIngeniería de Software",
            ],
        );

        DirectorAcademicProfile::updateOrCreate(
            ['user_id' => $directorJulian->id],
            [
                'research_lines' => [
                    'Inteligencia Artificial aplicada',
                    'Ingeniería de Software',
                    'Sistemas de información académicos',
                ],
                'technologies' => ['Laravel', 'React', 'PostgreSQL', 'Python'],
                'methodologies' => ['SCRUM', 'Design Science Research'],
                'academic_experience' => 'Dirección de proyectos de grado en desarrollo de software e IA aplicada a educación.',
                'years_of_experience' => 8,
            ],
        );

        // ──  Evaluador Externo (login externo con credenciales) ────────────────

        $evaluadorExternoAngel = User::updateOrCreate(
            ['email' => 'miguelafanquin10.evaluador@gmail.com'],
            [
                'name'                => 'Angel Afanador',
                'password'            => 'Pruebas123!',
                'role'                => UserRole::EvaluadorExterno,
                'es_externo'          => true,
                // Avoid forced password-change gate on first login (dev seed).
                'password_changed_at' => now(),
                // Clear lockout leftover from failed login attempts.
                'failed_attempts'     => 0,
                'last_failed_at'      => null,
                'locked_until'        => null,
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

        // Asignar evaluador externo Angel al proyecto demo
        EvaluadorProyecto::updateOrCreate(
            [
                'proyecto_id'  => $proyecto->id,
                'evaluador_id' => $evaluadorExternoAngel->id,
            ],
            [
                'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
                'assigned_at'       => now(),
                'fecha'             => now()->toDateString(),
                'hora_inicio'       => '09:00',
                'hora_fin'          => '10:00',
                'fase'              => 'Anteproyecto',
            ],
        );

        $this->command->info(sprintf(
            'Test users seeded: 2 coordinadores, 2 estudiantes, 1 director, 1 evaluador externo, 1 proyecto (%s)',
            $proyecto->code,
        ));
    }
}
