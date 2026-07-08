<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Migration test: composite (action, created_at) index for the
 * AuditLogController@index coordinator filter (T-024).
 *
 * Without this index, a 100k-row filter by `action=login.success`
 * between two dates would scan the full table. With it, MySQL/PG
 * can range-scan the (action, created_at) B-tree.
 */
it('audit_logs has a composite index on (action, created_at)', function () {
    expect(Schema::hasIndex('audit_logs', 'audit_logs_action_created_at_index'))
        ->toBeTrue();
});
