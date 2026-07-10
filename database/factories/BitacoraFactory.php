<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bitacora;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bitacora>
 */
class BitacoraFactory extends Factory
{
    protected $model = Bitacora::class;

    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'topic' => fake()->sentence(3),
            'notes' => fake()->paragraph(),
            'meeting_date' => fake()->date(),
            'duration_hours' => fake()->randomFloat(2, 0.5, 4),
            'signature_status' => 'Pendiente',
        ];
    }
}
