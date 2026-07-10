<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Proyecto;
use App\Models\Semestre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proyecto>
 */
class ProyectoFactory extends Factory
{
    protected $model = Proyecto::class;

    public function definition(): array
    {
        return [
            'semester_id' => fn () => Semestre::factory()->create()->id,
            'title' => fake()->unique()->sentence(4),
            'director_id' => null,
            'current_phase' => 'anteproyecto',
            'status' => 'en_curso',
            'requires_group_justification' => false,
        ];
    }
}
