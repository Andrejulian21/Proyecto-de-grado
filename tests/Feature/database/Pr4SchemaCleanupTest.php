<?php

declare(strict_types=1);

use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| PR 4 — DB Schema + Cleanup (H-012 through H-014)
|--------------------------------------------------------------------------
*/

// -- H-012: Indexes on authorized_emails.created_by --

test('authorized_emails.created_by has an index', function () {
    $indexes = collect(Schema::getIndexes('authorized_emails'));
    $hasCreatedAtIndex = $indexes->contains(fn ($idx) =>
        collect($idx['columns'])->contains('created_by')
    );

    expect($hasCreatedAtIndex)->toBeTrue();
});

// -- H-012: lower(email) functional index on users --

test('users has a lower(email) functional index', function () {
    $indexes = collect(Schema::getIndexes('users'));
    $hasLowerEmailIndex = $indexes->contains(fn ($idx) =>
        str_contains($idx['name'], 'users_email_lower_index')
        || collect($idx['columns'])->contains(fn ($c) => str_contains($c, 'lower'))
    );

    expect($hasLowerEmailIndex)->toBeTrue();
});

// -- H-012: lower(email) functional index on authorized_emails --

test('authorized_emails has a lower(email) functional index', function () {
    $indexes = collect(Schema::getIndexes('authorized_emails'));
    $hasLowerEmailIndex = $indexes->contains(fn ($idx) =>
        str_contains($idx['name'], 'authorized_emails_email_lower_index')
        || collect($idx['columns'])->contains(fn ($c) => str_contains($c, 'lower'))
    );

    expect($hasLowerEmailIndex)->toBeTrue();
});

// -- H-012: Soft deletes on authorized_emails --

test('authorized_emails has a deleted_at column for soft deletes', function () {
    expect(Schema::hasColumn('authorized_emails', 'deleted_at'))->toBeTrue();
});

test('authorized_email soft-deletes correctly', function () {
    $entry = AuthorizedEmail::factory()->create();

    $entry->delete();

    // Should not be found in default query
    expect(AuthorizedEmail::find($entry->id))->toBeNull();
    // Should be retrievable with trashed
    expect(AuthorizedEmail::withTrashed()->find($entry->id))->not->toBeNull();
    expect(AuthorizedEmail::withTrashed()->find($entry->id)->deleted_at)->not->toBeNull();
});

// -- H-012: AuthorizedEmail uses SoftDeletes trait --

test('AuthorizedEmail model uses SoftDeletes trait', function () {
    $traits = class_uses(AuthorizedEmail::class);
    expect($traits)->toContain('Illuminate\Database\Eloquent\SoftDeletes');
});

// -- H-013: CHECK constraint on users.role --

test('users.role CHECK constraint rejects invalid role values', function () {
    $user = User::factory()->create();

    // Attempt to insert an invalid role via raw query to bypass Eloquent casting
    expect(fn () => DB::statement(
        "INSERT INTO users (name, email, password, role, es_externo, created_at, updated_at)
         VALUES ('Invalid', 'invalid@test.com', 'password', 'InvalidRole', false, now(), now())"
    ))->toThrow(\Illuminate\Database\QueryException::class);
});

test('users.role CHECK constraint exists', function () {
    $constraints = DB::select("
        SELECT conname
        FROM pg_catalog.pg_constraint
        WHERE conrelid = 'users'::regclass
          AND contype = 'c'
          AND conname = 'users_role_check'
    ");

    expect($constraints)->not->toBeEmpty();
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'CHECK constraint verification only available on PostgreSQL');

// -- H-013: Redundant action index dropped --

test('redundant audit_logs_action_index no longer exists', function () {
    expect(Schema::hasIndex('audit_logs', 'audit_logs_action_index'))->toBeFalse();
});

test('composite (action, created_at) index still exists', function () {
    expect(Schema::hasIndex('audit_logs', 'audit_logs_action_created_at_index'))->toBeTrue();
});

// -- H-014: ExampleTest stubs removed --

test('Unit ExampleTest stub has been removed', function () {
    expect(file_exists(base_path('tests/Unit/ExampleTest.php')))->toBeFalse();
});

test('Feature ExampleTest has real tests or has been removed', function () {
    $path = base_path('tests/Feature/ExampleTest.php');
    if (file_exists($path)) {
        $content = file_get_contents($path);
        // It should NOT be the default stub — must contain meaningful assertions
        expect($content)->toContain('api/health');
    }
});

// -- H-014: like → ilike in UserController --

test('UserController usuarios query uses ilike instead of like for case-insensitive search', function () {
    $path = app_path('Http/Controllers/Admin/UserController.php');
    $content = file_get_contents($path);

    // Should use ilike for PostgreSQL case-insensitive search
    expect(str_contains($content, "'ilike'"))->toBeTrue()
        ->and(str_contains($content, "'like'"))->toBeFalse();
});

// -- H-014: sslmode default to require --

test('database config pgsql sslmode defaults to require', function () {
    $config = require config_path('database.php');
    $sslmode = $config['connections']['pgsql']['sslmode'] ?? null;

    expect($sslmode)->toBe(env('DB_SSLMODE', 'require'));
});

// -- H-014: extractHostedDomain collapsed branches --

test('extractHostedDomain has no redundant branches', function () {
    $path = app_path('Http/Controllers/Auth/AuthController.php');
    $content = file_get_contents($path);

    // Count occurrences of isset($googleUser->user['hd']) — should be exactly 1
    $matches = [];
    preg_match_all('/\$googleUser->user\[\'hd\'\]/', $content, $matches);
    $count = count($matches[0]);

    // The collapsed version references user['hd'] only once
    expect($count)->toBeLessThanOrEqual(1);
});
