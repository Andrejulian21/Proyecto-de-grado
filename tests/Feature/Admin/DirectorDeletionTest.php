<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::factory()->create();
});

function proyectoParaDirector(User $director, Semestre $semestre): Proyecto
{
    return Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
}

// -- RF-DIR-01 / RF-DIR-02 ------------------------------------------------

it('director sin proyectos puede eliminarse', function () {
    $director = User::factory()->director()->create();

    $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/usuarios/{$director->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Usuario eliminado');

    expect(User::query()->find($director->id))->toBeNull();
});

it('director con proyectos no puede eliminarse directamente', function () {
    $director = User::factory()->director()->create();
    $otro = User::factory()->director()->create();
    $p1 = proyectoParaDirector($director, $this->semestre);
    $p2 = proyectoParaDirector($director, $this->semestre);

    $response = $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/usuarios/{$director->id}");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'director_has_projects')
        ->assertJsonPath('proyectos_count', 2)
        ->assertJsonPath('can_reassign', true);

    expect($response->json('message'))->toBeString()->toContain('proyecto');
    expect(User::query()->find($director->id))->not->toBeNull();
    expect($p1->fresh()->director_id)->toBe($director->id);
    expect($p2->fresh()->director_id)->toBe($director->id);
    expect(User::query()->find($otro->id))->not->toBeNull();
});

it('director con proyectos y sin otros directores informa can_reassign false', function () {
    $director = User::factory()->director()->create();
    proyectoParaDirector($director, $this->semestre);

    $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/usuarios/{$director->id}")
        ->assertStatus(422)
        ->assertJsonPath('error', 'director_has_projects')
        ->assertJsonPath('can_reassign', false);
});

it('whitelist destroy de director con proyectos también se bloquea', function () {
    $director = User::factory()->director()->create(['email' => 'dir.guard@unab.edu.co']);
    proyectoParaDirector($director, $this->semestre);
    $entry = AuthorizedEmail::create([
        'email' => $director->email,
        'role' => UserRole::Director->value,
    ]);

    $this->actingAs($this->coordinador)
        ->deleteJson("/api/admin/whitelist/{$entry->id}")
        ->assertStatus(422)
        ->assertJsonPath('error', 'director_has_projects');

    expect(User::query()->find($director->id))->not->toBeNull();
    expect(AuthorizedEmail::query()->find($entry->id))->not->toBeNull();
});

// -- RF-DIR-03 ------------------------------------------------------------

it('director con proyectos no puede cambiar de rol', function () {
    $director = User::factory()->director()->create();
    proyectoParaDirector($director, $this->semestre);

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$director->id}", ['role' => 'Estudiante'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'director_has_projects');

    expect($director->fresh()->role)->toBe(UserRole::Director);
});

it('director sin proyectos puede cambiar de rol', function () {
    $director = User::factory()->director()->create();

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/usuarios/{$director->id}", ['role' => 'Estudiante'])
        ->assertOk();

    expect($director->fresh()->role)->toBe(UserRole::Estudiante);
});

it('whitelist no cambia el rol de un director con proyectos', function () {
    $director = User::factory()->director()->create(['email' => 'dir.rol@unab.edu.co']);
    proyectoParaDirector($director, $this->semestre);
    $entry = AuthorizedEmail::create([
        'email' => $director->email,
        'role' => UserRole::Director->value,
    ]);

    $this->actingAs($this->coordinador)
        ->putJson("/api/admin/whitelist/{$entry->id}", ['role' => 'Estudiante'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'director_has_projects');

    expect($director->fresh()->role)->toBe(UserRole::Director);
});

// -- RF-DIR-04 / RF-DIR-05 ------------------------------------------------

it('director con proyectos puede eliminarse con reasignación aleatoria', function () {
    $saliente = User::factory()->director()->create();
    $receptorA = User::factory()->director()->create();
    $receptorB = User::factory()->director()->create();
    $proyectos = collect([
        proyectoParaDirector($saliente, $this->semestre),
        proyectoParaDirector($saliente, $this->semestre),
        proyectoParaDirector($saliente, $this->semestre),
        proyectoParaDirector($saliente, $this->semestre),
    ]);

    $idsReceptores = [$receptorA->id, $receptorB->id];

    $response = $this->actingAs($this->coordinador)
        ->postJson("/api/admin/usuarios/{$saliente->id}/eliminar-con-reasignacion");

    $response->assertOk();
    expect($response->json('message'))->toContain('reasign');
    expect(User::query()->find($saliente->id))->toBeNull();

    foreach ($proyectos as $proyecto) {
        $fresh = $proyecto->fresh();
        expect($fresh)->not->toBeNull();
        expect($fresh->director_id)->not->toBe($saliente->id);
        expect($idsReceptores)->toContain($fresh->director_id);
    }
});

it('un único receptor recibe todos los proyectos del director eliminado', function () {
    $saliente = User::factory()->director()->create();
    $receptor = User::factory()->director()->create();
    $p1 = proyectoParaDirector($saliente, $this->semestre);
    $p2 = proyectoParaDirector($saliente, $this->semestre);

    $this->actingAs($this->coordinador)
        ->postJson("/api/admin/usuarios/{$saliente->id}/eliminar-con-reasignacion")
        ->assertOk();

    expect($p1->fresh()->director_id)->toBe($receptor->id);
    expect($p2->fresh()->director_id)->toBe($receptor->id);
    expect(User::query()->find($saliente->id))->toBeNull();
});

// -- RF-DIR-06 ------------------------------------------------------------

it('sin otros directores disponibles la reasignación es rechazada', function () {
    $unico = User::factory()->director()->create();
    $proyecto = proyectoParaDirector($unico, $this->semestre);

    $this->actingAs($this->coordinador)
        ->postJson("/api/admin/usuarios/{$unico->id}/eliminar-con-reasignacion")
        ->assertStatus(422)
        ->assertJsonPath('error', 'no_directors_available');

    expect(User::query()->find($unico->id))->not->toBeNull();
    expect($proyecto->fresh()->director_id)->toBe($unico->id);
});

it('estudiante no puede invocar la reasignación', function () {
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($estudiante)
        ->postJson("/api/admin/usuarios/{$director->id}/eliminar-con-reasignacion")
        ->assertStatus(403);
});

it('no-director no puede usarse en eliminar-con-reasignacion', function () {
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->actingAs($this->coordinador)
        ->postJson("/api/admin/usuarios/{$estudiante->id}/eliminar-con-reasignacion")
        ->assertStatus(422);

    expect(User::query()->find($estudiante->id))->not->toBeNull();
});
