<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacion>
 */
class NotificacionFactory extends Factory
{
    protected $model = Notificacion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sender_id' => User::factory(),
            'type' => fake()->randomElement(['info', 'alerta', 'recordatorio', 'aprobacion', 'rechazo']),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'is_read' => false,
            'sent_at' => now(),
        ];
    }
}
