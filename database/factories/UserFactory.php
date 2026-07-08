<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Default attributes. `role` defaults to Estudiante because it's the
     * most common user type. `es_externo` is false by default. Tests that
     * need other roles or external users should pass explicit values or
     * use the `coordinador()` / `external()` named states below.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Estudiante->value,
            'es_externo' => false,
            'google_id' => null,
            'avatar' => null,
            'last_activity_at' => null,
            'totp_secret' => null,
            'password_changed_at' => now(), // internal users don't need to change
            'failed_attempts' => 0,
            'locked_until' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the model as a Coordinador. Useful when seeding or
     * testing admin-only paths.
     */
    public function coordinador(): static
    {
        return $this->state(fn () => ['role' => UserRole::Coordinador->value]);
    }

    /**
     * Configure the model as a Director.
     */
    public function director(): static
    {
        return $this->state(fn () => ['role' => UserRole::Director->value]);
    }

    /**
     * Configure the model as an EvaluadorExterno with a real password
     * (es_externo = true). These users log in at /login/externo.
     */
    public function external(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::EvaluadorExterno->value,
            'es_externo' => true,
            'google_id' => null,
            // External evaluators start with `password_changed_at = null`
            // so the first login forces a password change.
            'password_changed_at' => null,
        ]);
    }
}
