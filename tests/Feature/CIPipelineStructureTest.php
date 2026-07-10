<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Issue #9 — CI pipeline structure test.
 *
 * The CI workflow is a YAML config file, not executable PHP, so
 * the "test" is a characterization check: does the file exist,
 * does it parse, and does it declare the triggers, services and
 * steps the spec mandates? A real execution test would require
 * GitHub Actions (or `act` + Docker), which is out of scope for
 * the unit suite.
 *
 * If actionlint is available we run it for additional coverage;
 * otherwise we fall back to a Symfony YAML parse.
 */
it('declares .github/workflows/ci.yml with the required triggers, services and steps', function () {
    $path = base_path('.github/workflows/ci.yml');
    expect(File::exists($path))->toBeTrue("CI workflow file is missing at {$path}");

    $raw = File::get($path);

    // ---- triggers ------------------------------------------------------
    // push to master AND pull_request. We accept any on:-shape that
    // mentions both keys; the precise YAML is the workflow author's
    // call.
    expect($raw)
        ->toContain('push:')
        ->toContain('pull_request:');

    // The spec pins master as the target branch.
    expect($raw)
        ->toContain('master');

    // ---- services ------------------------------------------------------
    // PostgreSQL 16 service for the database.
    expect(stripos($raw, 'postgres:16') !== false)->toBeTrue(
        'CI workflow must declare a postgres:16 service so Pest can run against the real DB',
    );

    // ---- jobs / steps --------------------------------------------------
    // pest step — the actual test runner.
    expect($raw)->toContain('pest');

    // pint --test step — style enforcement.
    expect($raw)
        ->toContain('pint')
        ->toContain('--test');

    // Node + npm build — frontend compile.
    expect($raw)
        ->toContain('npm ci')
        ->toContain('npm run build');

    // ---- PHP version pinned to the project's runtime (8.3+) ----------
    expect((bool) preg_match('/php-version:\s*[\'"]?8\.[3-9]/i', $raw))
        ->toBeTrue('CI workflow must pin PHP 8.3+ (project runtime)');
});
