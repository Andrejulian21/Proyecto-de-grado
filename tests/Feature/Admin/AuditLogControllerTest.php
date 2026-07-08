<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle: AuditLogController (T-024).
 *
 *   GET /api/admin/audit-logs → paginated, filterable list
 *
 * Gated by `role:Coordinador` — only Coordinadores may view the
 * immutable audit trail.
 */

it('returns a paginated list of audit logs', function () {
    $coord = User::factory()->coordinador()->create();
    AuditLog::create(['action' => 'login.success', 'description' => 'first']);
    AuditLog::create(['action' => 'login.success', 'description' => 'second']);
    AuditLog::create(['action' => 'login.rejected', 'description' => 'third']);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'user_id', 'action', 'description', 'ip_address', 'user_agent', 'metadata', 'created_at']],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

it('filters by action type', function () {
    $coord = User::factory()->coordinador()->create();
    AuditLog::create(['action' => 'login.success', 'description' => 'ok']);
    AuditLog::create(['action' => 'login.rejected', 'description' => 'fail']);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs?action=login.rejected');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.action'))->toBe('login.rejected');
});

it('filters by user_id', function () {
    $coord = User::factory()->coordinador()->create();
    $target = User::factory()->create();
    $other = User::factory()->create();

    AuditLog::create(['user_id' => $target->id, 'action' => 'login.success']);
    AuditLog::create(['user_id' => $other->id, 'action' => 'login.success']);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs?user_id='.$target->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.user_id'))->toBe($target->id);
});

it('filters by date range', function () {
    $coord = User::factory()->coordinador()->create();
    // Bypass model immutability guards for test data seeding.
    // created_at is not in $fillable, so we use a raw insert
    // to create an "old" audit entry outside the date_from window.
    \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
        'action' => 'login.success',
        'description' => 'old',
        'created_at' => now()->subDays(10),
    ]);
    AuditLog::create(['action' => 'login.success', 'description' => 'today']);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs?date_from='.now()->subDays(5)->format('Y-m-d'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.description'))->toBe('today');
});

it('orders results by created_at DESC', function () {
    $coord = User::factory()->coordinador()->create();
    $first = AuditLog::create(['action' => 'login.success', 'created_at' => now()->subHour()]);
    $second = AuditLog::create(['action' => 'login.success', 'created_at' => now()]);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs');

    $response->assertOk();
    expect((int) $response->json('data.0.id'))->toBe($second->id)
        ->and((int) $response->json('data.1.id'))->toBe($first->id);
});

it('respects per_page parameter with a max of 200', function () {
    $coord = User::factory()->coordinador()->create();
    AuditLog::create(['action' => 'login.success']);
    AuditLog::create(['action' => 'login.rejected']);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs?per_page=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('per_page'))->toBe(1);
});

it('non-coordinador cannot view audit logs (403)', function () {
    $student = User::factory()->create(['role' => UserRole::Estudiante->value]);
    AuditLog::create(['action' => 'login.success']);

    $this->actingAs($student)
        ->getJson('/api/admin/audit-logs')
        ->assertStatus(403);
});

it('returns empty list when no logs match the filter', function () {
    $coord = User::factory()->coordinador()->create();
    AuditLog::create(['action' => 'login.success']);

    $response = $this->actingAs($coord)
        ->getJson('/api/admin/audit-logs?action=nonexistent');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});
