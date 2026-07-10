<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecursoInformativo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecursoInformativo>
 */
class RecursoInformativoFactory extends Factory
{
    protected $model = RecursoInformativo::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(['guia', 'formato', 'tutorial', 'normatividad', 'plantilla']),
            'description' => fake()->paragraph(),
            'link' => fake()->url(),
            'access_count' => 0,
        ];
    }
}
