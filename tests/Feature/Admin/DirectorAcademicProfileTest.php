<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\DirectorAcademicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->create(['role' => UserRole::Coordinador->value]);
    $this->director = User::factory()->create([
        'role' => UserRole::Director->value,
        'email' => 'director.perfil@unab.edu.co',
        'name' => 'Director Perfil',
    ]);
    $this->estudiante = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'email' => 'est.perfil@unab.edu.co',
    ]);
});

it('crea director con perfil academico desde whitelist', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/whitelist', [
            'email' => 'nuevo.director@unab.edu.co',
            'name' => 'Nuevo Director',
            'role' => 'Director',
            'areas' => "IA\nSoftware",
            'research_lines_text' => "IA educativa\nSistemas de información",
            'technologies_text' => "Python\nLaravel",
            'methodologies_text' => 'SCRUM',
            'academic_experience' => 'Dirección de proyectos de grado en IA.',
            'years_of_experience' => 10,
        ]);

    $response->assertCreated();

    $user = User::where('email', 'nuevo.director@unab.edu.co')->first();
    expect($user)->not->toBeNull()
        ->and($user->areas)->toContain('IA');

    $profile = DirectorAcademicProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->research_lines)->toContain('IA educativa')
        ->and($profile->technologies)->toContain('Python')
        ->and($profile->methodologies)->toContain('SCRUM')
        ->and($profile->academic_experience)->toBe('Dirección de proyectos de grado en IA.')
        ->and($profile->years_of_experience)->toBe(10);
});

it('actualiza perfil academico de un director', function () {
    $response = $this->actingAs($this->coordinador)
        ->putJson("/api/admin/directores/{$this->director->id}/perfil-academico", [
            'areas' => 'Datos',
            'research_lines' => ['Analítica'],
            'technologies' => ['PostgreSQL'],
            'methodologies' => ['Design Science Research'],
            'academic_experience' => 'Experto en datos.',
            'years_of_experience' => 7,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.areas', 'Datos')
        ->assertJsonPath('data.research_lines.0', 'Analítica')
        ->assertJsonPath('data.years_of_experience', 7);

    $this->director->refresh();
    expect($this->director->areas)->toBe('Datos');
    expect(DirectorAcademicProfile::where('user_id', $this->director->id)->exists())->toBeTrue();
});

it('obtiene perfil academico vacio cuando no existe fila', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson("/api/admin/directores/{$this->director->id}/perfil-academico");

    $response->assertOk()
        ->assertJsonPath('data.user_id', $this->director->id)
        ->assertJsonPath('data.research_lines', []);
});

it('rechaza perfil academico para no director', function () {
    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/directores/{$this->estudiante->id}/perfil-academico", [
            'areas' => 'X',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'not_director');
});

it('exige rol coordinador', function () {
    $this->actingAs($this->director)
        ->getJson("/api/admin/directores/{$this->director->id}/perfil-academico")
        ->assertForbidden();
});

it('sigue permitiendo crear estudiante sin campos de perfil', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/whitelist', [
            'email' => 'nuevo.estudiante@unab.edu.co',
            'name' => 'Nuevo Estudiante',
            'role' => 'Estudiante',
            'codigo_estudiante' => 'U123',
        ]);

    $response->assertCreated();
    expect(DirectorAcademicProfile::query()->count())->toBe(0);
});
