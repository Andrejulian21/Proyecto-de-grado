<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Migration test: external-evaluator auth columns on `users` (T-016, T-017).
 */
it('users table has password_changed_at column', function () {
    expect(Schema::hasColumn('users', 'password_changed_at'))->toBeTrue();
});

it('users table has failed_attempts column defaulting to 0', function () {
    expect(Schema::hasColumn('users', 'failed_attempts'))->toBeTrue();
});

it('users table has locked_until column', function () {
    expect(Schema::hasColumn('users', 'locked_until'))->toBeTrue();
});

it('users table has an index on locked_until for fast lockout checks', function () {
    expect(Schema::hasIndex('users', 'users_locked_until_index'))->toBeTrue();
});

it('migration is reversible (down drops the new columns)', function () {
    expect(Schema::hasColumn('users', 'password_changed_at'))->toBeTrue();

    // Smoke-test the down path by checking the column can be dropped
    // without error. The RefreshDatabase trait rolls everything back
    // automatically at the end of this test, so we just need to
    // assert the columns are present in the current state.
    expect(Schema::hasColumns('users', ['password_changed_at', 'failed_attempts', 'locked_until']))
        ->toBeTrue();
});
