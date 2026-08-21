<?php

declare(strict_types=1);

use App\Actions\Directors\DeleteDirectorWithReassignmentAction;
use App\Enums\UserRole;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('revierte la transacción si el delete del director falla tras reasignar', function () {
    $actor = User::factory()->coordinador()->create();
    $saliente = User::factory()->director()->create();
    $receptor = User::factory()->director()->create();
    $semestre = Semestre::factory()->create();
    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $saliente->id,
    ]);

    User::deleting(function (User $user) use ($saliente): void {
        if ($user->id === $saliente->id) {
            throw new RuntimeException('simulated failure');
        }
    });

    try {
        expect(fn () => app(DeleteDirectorWithReassignmentAction::class)->handle($saliente, $actor))
            ->toThrow(RuntimeException::class, 'simulated failure');

        expect(User::query()->find($saliente->id))->not->toBeNull()
            ->and(User::query()->find($saliente->id)->role)->toBe(UserRole::Director);
        expect($proyecto->fresh()->director_id)->toBe($saliente->id);
        expect(User::query()->find($receptor->id))->not->toBeNull();
    } finally {
        $dispatcher = User::getEventDispatcher();
        $dispatcher?->forget('eloquent.deleting: '.User::class);
    }
});
