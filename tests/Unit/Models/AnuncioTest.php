<?php

declare(strict_types=1);

use App\Models\Anuncio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Anuncio model exists and extends Model', function () {
    $anuncio = new Anuncio();
    expect($anuncio)->toBeInstanceOf(Illuminate\Database\Eloquent\Model::class);
});

test('Anuncio fillable fields work correctly', function () {
    $user = User::factory()->coordinador()->create();

    $anuncio = Anuncio::create([
        'author_id' => $user->id,
        'title' => 'Aviso importante',
        'content' => 'Contenido del aviso',
        'published_at' => now(),
        'is_active' => true,
    ]);

    expect($anuncio->title)->toBe('Aviso importante');
    expect($anuncio->content)->toBe('Contenido del aviso');
    expect($anuncio->author_id)->toBe($user->id);
});

test('Anuncio casts is_active to boolean', function () {
    $user = User::factory()->coordinador()->create();

    $anuncio = Anuncio::create([
        'author_id' => $user->id,
        'title' => 'Test boolean',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    expect($anuncio->fresh()->is_active)->toBeTrue();

    $anuncio->update(['is_active' => false]);
    expect($anuncio->fresh()->is_active)->toBeFalse();
});

test('Anuncio casts published_at to datetime', function () {
    $user = User::factory()->coordinador()->create();

    $anuncio = Anuncio::create([
        'author_id' => $user->id,
        'title' => 'Test fecha',
        'content' => 'Contenido',
        'published_at' => '2026-07-01 10:00:00',
    ]);

    expect($anuncio->published_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($anuncio->published_at->format('Y-m-d H:i'))->toBe('2026-07-01 10:00');
});

test('Anuncio belongs to User (author)', function () {
    $user = User::factory()->coordinador()->create();

    $anuncio = Anuncio::create([
        'author_id' => $user->id,
        'title' => 'Test relation',
        'content' => 'Contenido',
        'published_at' => now(),
    ]);

    expect($anuncio->author)->toBeInstanceOf(User::class);
    expect($anuncio->author->id)->toBe($user->id);
});
