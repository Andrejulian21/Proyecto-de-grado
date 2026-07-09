<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Semestre;
use Illuminate\Database\Seeder;

class SemestreSeeder extends Seeder
{
    public function run(): void
    {
        Semestre::create([
            'name' => '2025-1',
            'start_date' => '2025-02-01',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);

        Semestre::create([
            'name' => '2025-2',
            'start_date' => '2025-08-01',
            'end_date' => '2025-11-30',
            'is_active' => false,
        ]);

        Semestre::create([
            'name' => '2026-1',
            'start_date' => '2026-02-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        Semestre::create([
            'name' => '2026-2',
            'start_date' => '2026-08-01',
            'end_date' => '2026-11-30',
            'is_active' => true,
        ]);
    }
}
