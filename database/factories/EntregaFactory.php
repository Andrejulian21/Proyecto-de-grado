<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entrega;
use App\Models\Semestre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrega>
 */
class EntregaFactory extends Factory
{
    protected $model = Entrega::class;

    public function definition(): array
    {
        return [
            'semester_id' => fn () => Semestre::factory(),
            'title' => fake()->sentence(3),
            'phase' => 'anteproyecto',
            'due_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'status' => 'pendiente',
            'description' => fake()->paragraph(),
            'evaluation_complete' => false,
        ];
    }
}
