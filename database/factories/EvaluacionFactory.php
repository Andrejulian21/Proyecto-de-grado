<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluacion>
 */
class EvaluacionFactory extends Factory
{
    protected $model = Evaluacion::class;

    public function definition(): array
    {
        return [
            'entrega_id' => Entrega::factory(),
            'evaluador_id' => User::factory(),
            'criterio' => fake()->sentence(3),
            'percentage' => fake()->randomElement([25, 25, 25, 25, 30, 30, 40, 50]),
            'grade' => fake()->randomFloat(2, 0, 5),
            'comment' => fake()->optional(0.7)->paragraph(),
            'evaluated_at' => now(),
        ];
    }
}
