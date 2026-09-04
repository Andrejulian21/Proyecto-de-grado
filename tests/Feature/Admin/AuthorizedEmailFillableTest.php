<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =========================================================================
// H-008: AuthorizedEmail::$fillable includes 'name'
// =========================================================================

it('stores name when creating an AuthorizedEmail via fillable', function () {
    $entry = AuthorizedEmail::create([
        'email' => 'test@unab.edu.co',
        'name' => 'Juan Perez',
        'role' => UserRole::Estudiante,
        'created_by' => null,
    ]);

    expect($entry->name)->toBe('Juan Perez');
});

it('returns name when retrieved from database', function () {
    $entry = AuthorizedEmail::create([
        'email' => 'test@unab.edu.co',
        'name' => 'Maria Gomez',
        'role' => UserRole::Director,
        'created_by' => null,
    ]);

    $fresh = AuthorizedEmail::query()->find($entry->id);

    expect($fresh->name)->toBe('Maria Gomez');
});
