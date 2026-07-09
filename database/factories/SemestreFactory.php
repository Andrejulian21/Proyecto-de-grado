<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Semestre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semestre>
 */
class SemestreFactory extends Factory
{
    protected $model = Semestre::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->regexify('[0-9]{4}-[12]'),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'is_active' => true,
        ];
    }
}
