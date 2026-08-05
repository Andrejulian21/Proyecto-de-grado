<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to test it at some point. As such, you can use the "TestCase" class provided by Pest.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| RefreshDatabase for Feature tests
|--------------------------------------------------------------------------
|
| Every Feature test gets a clean in-memory SQLite database (see
| phpunit.xml — `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`).
|
*/

uses(RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Check if a column has decimal/numeric type. SQLite reports "numeric" and
 * PostgreSQL reports "decimal" / "numeric" depending on the variant.
 *
 * Used by the database schema tests so they can stay DB-agnostic across
 * the in-memory SQLite test env (phpunit.xml) and the production
 * PostgreSQL driver.
 */
function columnIsDecimalLike(?array $col): bool
{
    if ($col === null) {
        return false;
    }

    $type = strtolower((string) ($col['type'] ?? $col['type_name'] ?? ''));

    return str_contains($type, 'numeric') || str_contains($type, 'decimal');
}

/**
 * Check if a column has boolean type. SQLite reports "tinyint(1)" (Laravel's
 * boolean translation) and PostgreSQL reports "bool".
 */
function columnIsBooleanLike(?array $col): bool
{
    if ($col === null) {
        return false;
    }

    $type = strtolower((string) ($col['type'] ?? $col['type_name'] ?? ''));

    return str_contains($type, 'bool')
        || str_contains($type, 'tinyint');
}

/**
 * Normalize a column default value for comparison. SQLite wraps string/char
 * defaults in single quotes; we strip them. PHP booleans pass through.
 */
function normalizeColumnDefault(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    $s = (string) $value;

    if (strlen($s) >= 2 && $s[0] === "'" && substr($s, -1) === "'") {
        $s = substr($s, 1, -1);
    }

    return trim($s);
}
