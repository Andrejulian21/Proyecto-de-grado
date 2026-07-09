<?php

use App\Models\User;
use App\Models\AuthorizedEmail;

User::create([
    'name' => 'Coordinador UNAB',
    'email' => 'coordinador@unab.edu.co',
    'role' => 'Coordinador',
    'password' => bcrypt('password'),
    'last_activity_at' => now(),
]);

User::create([
    'name' => 'Estudiante UNAB',
    'email' => 'estudiante@unab.edu.co',
    'role' => 'Estudiante',
    'password' => bcrypt('password'),
    'last_activity_at' => now(),
]);

AuthorizedEmail::create([
    'email' => 'coordinador@unab.edu.co',
    'role' => 'Coordinador',
]);

AuthorizedEmail::create([
    'email' => 'estudiante@unab.edu.co',
    'role' => 'Estudiante',
]);

echo "Seed OK\n";
