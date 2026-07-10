<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Anuncio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Anuncio>
 */
class AnuncioFactory extends Factory
{
    protected $model = Anuncio::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'published_at' => now(),
            'is_active' => true,
        ];
    }
}
