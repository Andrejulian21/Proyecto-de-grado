<?php

declare(strict_types=1);

use App\Enums\UserRole;

test('UserRole enum has exactly four cases', function () {
    expect(UserRole::cases())->toHaveCount(4);
});

test('UserRole cases are the four roles defined in the spec', function () {
    expect(array_map(fn ($c) => $c->name, UserRole::cases()))->toBe([
        'Estudiante',
        'Director',
        'Coordinador',
        'EvaluadorExterno',
    ]);
});

test('UserRole backed values are the Spanish role names as strings', function () {
    // The DB column stores the string backing value, never the case name.
    // The names intentionally match so we can interchange them.
    expect(UserRole::Estudiante->value)->toBe('Estudiante');
    expect(UserRole::Director->value)->toBe('Director');
    expect(UserRole::Coordinador->value)->toBe('Coordinador');
    expect(UserRole::EvaluadorExterno->value)->toBe('EvaluadorExterno');
});

test('UserRole::tryFrom returns the correct case for a valid string', function () {
    expect(UserRole::tryFrom('Coordinador'))->toBe(UserRole::Coordinador);
    expect(UserRole::tryFrom('Estudiante'))->toBe(UserRole::Estudiante);
    expect(UserRole::tryFrom('Director'))->toBe(UserRole::Director);
    expect(UserRole::tryFrom('EvaluadorExterno'))->toBe(UserRole::EvaluadorExterno);
});

test('UserRole::tryFrom returns null for an unknown string', function () {
    expect(UserRole::tryFrom('Admin'))->toBeNull();
    expect(UserRole::tryFrom('coordinador'))->toBeNull(); // case sensitive
    expect(UserRole::tryFrom(''))->toBeNull();
});

test('UserRole::values() helper returns all string values for validation rules', function () {
    // Used by FormRequest rules: 'in:'.implode(',', UserRole::values())
    expect(UserRole::values())->toBe([
        'Estudiante',
        'Director',
        'Coordinador',
        'EvaluadorExterno',
    ]);
});

test('UserRole::isInternal() distinguishes UNAB roles from external evaluators', function () {
    expect(UserRole::Estudiante->isInternal())->toBeTrue();
    expect(UserRole::Director->isInternal())->toBeTrue();
    expect(UserRole::Coordinador->isInternal())->toBeTrue();
    expect(UserRole::EvaluadorExterno->isInternal())->toBeFalse();
});
