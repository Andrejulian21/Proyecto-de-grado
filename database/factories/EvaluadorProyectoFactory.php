<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluadorProyecto>
 */
class EvaluadorProyectoFactory extends Factory
{
    protected $model = EvaluadorProyecto::class;

    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'evaluador_id' => User::factory(),
            'invitation_status' => 'Pendiente',
            'assigned_at' => now(),
            'evaluado' => false,
        ];
    }
}
