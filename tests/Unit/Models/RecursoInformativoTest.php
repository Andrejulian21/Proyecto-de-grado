<?php

declare(strict_types=1);

use App\Models\RecursoInformativo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('RecursoInformativo model exists and extends Model', function () {
    $recurso = new RecursoInformativo;
    expect($recurso)->toBeInstanceOf(Model::class);
});

test('RecursoInformativo fillable fields work correctly', function () {
    $user = User::factory()->coordinador()->create();

    $recurso = RecursoInformativo::create([
        'author_id' => $user->id,
        'title' => 'Guía de estilo',
        'category' => 'documentacion',
        'description' => 'Guía de estilo para el proyecto',
        'file_path' => 'files/guia.pdf',
        'link' => null,
    ]);

    expect($recurso->title)->toBe('Guía de estilo');
    expect($recurso->category)->toBe('documentacion');
    expect($recurso->description)->toBe('Guía de estilo para el proyecto');
    expect($recurso->file_path)->toBe('files/guia.pdf');
    expect($recurso->link)->toBeNull();
});

test('RecursoInformativo defaults access_count to 0', function () {
    $user = User::factory()->coordinador()->create();

    $recurso = RecursoInformativo::create([
        'author_id' => $user->id,
        'title' => 'Test',
        'category' => 'test',
    ]);

    expect($recurso->fresh()->access_count)->toBe(0);
});

test('RecursoInformativo can increment access_count', function () {
    $user = User::factory()->coordinador()->create();

    $recurso = RecursoInformativo::create([
        'author_id' => $user->id,
        'title' => 'Test',
        'category' => 'test',
    ]);

    $recurso->increment('access_count');
    expect($recurso->fresh()->access_count)->toBe(1);

    $recurso->increment('access_count');
    expect($recurso->fresh()->access_count)->toBe(2);
});

test('RecursoInformativo belongs to User (author)', function () {
    $user = User::factory()->coordinador()->create();

    $recurso = RecursoInformativo::create([
        'author_id' => $user->id,
        'title' => 'Test relation',
        'category' => 'test',
    ]);

    expect($recurso->author)->toBeInstanceOf(User::class);
    expect($recurso->author->id)->toBe($user->id);
});
