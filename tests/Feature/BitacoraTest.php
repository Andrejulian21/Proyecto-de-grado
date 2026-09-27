<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $this->proyecto->estudiantes()->attach($this->estudiante->id);
});

// ----------------------------------------------------------------------
// RF-WK-03rev: semana 1-32 user-declared with max + created_at throttle
// + unique meeting_date. Fixed 422 codes.
// ----------------------------------------------------------------------

it('a second create in the same real week (Mon-Sun by created_at) returns 422 WEEK_THROTTLE', function () {
    Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'First this week',
        'meeting_date' => '2026-04-01',
        'semana' => 1,
    ]);

    // Different semana AND different meeting week on purpose: the old
    // meeting_date-anchored check would let this through, the
    // created_at-anchored throttle must reject it.
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Second this week',
            'meeting_date' => '2026-05-20',
            'semana' => 2,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'WEEK_THROTTLE');
    expect(Bitacora::count())->toBe(1);
});

it('backdating meeting_date into another week does not bypass the throttle', function () {
    Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'First this week',
        'meeting_date' => '2026-04-01',
        'semana' => 1,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Backdated attempt',
            'meeting_date' => '2026-01-05',
            'semana' => 2,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'WEEK_THROTTLE');
});

it('semana greater than the proyecto max returns 422 SEMANA_EXCEEDS_MAX', function () {
    $seed = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Week five',
        'meeting_date' => '2026-04-01',
        'semana' => 5,
    ]);
    // Backdate out of the current real week so only the max rule fires.
    $seed->forceFill(['created_at' => now()->subWeek()])->save();

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Week seven skips six',
            'meeting_date' => '2026-06-01',
            'semana' => 7,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'SEMANA_EXCEEDS_MAX');
    expect(Bitacora::count())->toBe(1);
});

it('duplicate meeting_date returns 422 MEETING_DATE_DUPLICATE', function () {
    foreach ([5, 6] as $semana) {
        $seed = Bitacora::create([
            'proyecto_id' => $this->proyecto->id,
            'topic' => "Week {$semana}",
            'meeting_date' => $semana === 5 ? '2026-04-10' : '2026-04-11',
            'semana' => $semana,
        ]);
        $seed->forceFill(['created_at' => now()->subWeek()])->save();
    }

    // semana 7 is exactly max+1 so only the date rule fires.
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Same date replay',
            'meeting_date' => '2026-04-10',
            'semana' => 7,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MEETING_DATE_DUPLICATE');
    expect(Bitacora::count())->toBe(2);
});

it('semana out of range returns 422 SEMANA_RANGE', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Out of range',
            'meeting_date' => '2026-04-10',
            'semana' => 33,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'SEMANA_RANGE')
        ->assertJsonValidationErrors(['semana']);
});

it('duplicate semana returns 422 SEMANA_DUPLICATE', function () {
    $seed = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Original',
        'meeting_date' => '2026-04-01',
        'semana' => 10,
    ]);
    $seed->forceFill(['created_at' => now()->subWeek()])->save();

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Duplicated week',
            'meeting_date' => '2026-04-02',
            'semana' => 10,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'SEMANA_DUPLICATE')
        ->assertJsonValidationErrors(['semana']);
});

it('semana below the proyecto max returns 422 SEMANA_ANTERIOR', function () {
    $seed = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Week eight',
        'meeting_date' => '2026-04-01',
        'semana' => 8,
    ]);
    // Backdate out of the current real week so only the max rule fires.
    $seed->forceFill(['created_at' => now()->subWeek()])->save();

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Week six gap-fill attempt',
            'meeting_date' => '2026-06-02',
            'semana' => 6,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'SEMANA_ANTERIOR');
    expect(Bitacora::count())->toBe(1);
});

it('semana exactly max+1 succeeds', function () {
    $seed = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Week five',
        'meeting_date' => '2026-04-01',
        'semana' => 5,
    ]);
    // Backdate out of the current real week so the throttle does not fire.
    $seed->forceFill(['created_at' => now()->subWeek()])->save();

    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Week six follows five',
            'meeting_date' => '2026-06-03',
            'semana' => 6,
        ]);

    $response->assertStatus(201);
    expect(Bitacora::count())->toBe(2);
});
