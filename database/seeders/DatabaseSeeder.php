<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SemestreSeeder::class);

        // TestUsersSeeder usa datos de prueba personales: solo se ejecuta
        // en entornos local/testing (nunca en producción o staging).
        if (app()->environment(['local', 'testing'])) {
            $this->call(TestUsersSeeder::class);
        }

        $this->call(DemoDataSeeder::class);
    }
}
