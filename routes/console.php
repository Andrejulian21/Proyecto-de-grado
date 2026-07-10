<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Issue #11 — daily archive of audit log entries older than 5 years.
// The audit:archive command (app/Console/Commands/AuditArchive.php)
// uses `SET LOCAL session_replication_role = replica` to bypass
// the audit_logs immutability trigger for the duration of the
// archive transaction.
Schedule::command('audit:archive')->daily();
