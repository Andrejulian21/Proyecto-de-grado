<?php

declare(strict_types=1);

use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Notificacion model exists and extends Model', function () {
    $notificacion = new Notificacion();
    expect($notificacion)->toBeInstanceOf(Illuminate\Database\Eloquent\Model::class);
});

test('Notificacion fillable fields work correctly', function () {
    $user = User::factory()->create();
    $sender = User::factory()->coordinador()->create();

    $notificacion = Notificacion::create([
        'user_id' => $user->id,
        'sender_id' => $sender->id,
        'type' => 'entrega.creada',
        'title' => 'Nueva entrega',
        'content' => 'Se ha creado una nueva entrega.',
        'sent_at' => now(),
    ]);

    expect($notificacion->user_id)->toBe($user->id);
    expect($notificacion->type)->toBe('entrega.creada');
    expect($notificacion->title)->toBe('Nueva entrega');
    expect($notificacion->is_read)->toBeFalse();
});

test('Notificacion defaults is_read to false', function () {
    $user = User::factory()->create();

    $notificacion = Notificacion::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    expect($notificacion->fresh()->is_read)->toBeFalse();
});

test('Notificacion casts is_read to boolean', function () {
    $user = User::factory()->create();

    $notificacion = Notificacion::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    $notificacion->update(['is_read' => true]);
    expect($notificacion->fresh()->is_read)->toBeTrue();
});

test('Notificacion casts sent_at to datetime', function () {
    $user = User::factory()->create();

    $notificacion = Notificacion::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test',
        'content' => 'Contenido',
        'sent_at' => '2026-07-01 10:00:00',
    ]);

    expect($notificacion->sent_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($notificacion->sent_at->format('Y-m-d H:i'))->toBe('2026-07-01 10:00');
});

test('Notificacion belongs to User and nullable sender', function () {
    $user = User::factory()->create();

    $notificacion = Notificacion::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    expect($notificacion->user)->toBeInstanceOf(User::class);
    expect($notificacion->user->id)->toBe($user->id);
    expect($notificacion->sender)->toBeNull();

    $sender = User::factory()->coordinador()->create();
    $notificacion2 = Notificacion::create([
        'user_id' => $user->id,
        'sender_id' => $sender->id,
        'type' => 'test',
        'title' => 'Test',
        'content' => 'Contenido',
        'sent_at' => now(),
    ]);

    expect($notificacion2->sender)->toBeInstanceOf(User::class);
});
