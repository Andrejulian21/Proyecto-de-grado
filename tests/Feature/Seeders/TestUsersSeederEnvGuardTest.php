<?php

declare(strict_types=1);

use App\Models\AuthorizedEmail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Seeder;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
|--------------------------------------------------------------------------
| Issue #44 — TestUsersSeeder nunca debe ejecutarse fuera de local/testing.
|
| El seeder crea cuentas con datos de prueba personales y una contraseña
| compartida conocida: si se ejecuta contra un entorno desplegado se crean
| cuentas activas con credenciales expuestas. El guard de entorno evita
| eso (tanto en la invocación directa como vía DatabaseSeeder).
|--------------------------------------------------------------------------
*/

function makeFakeSeederCommand(): Command
{
    $command = new class extends Command
    {
        protected $name = 'test-seed-command';

        protected $signature = 'test-seed-command';
    };

    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new StringInput(''), new BufferedOutput()));

    return $command;
}

function runSeederWithCommand(Seeder $seeder, Command $command): void
{
    $seeder->setContainer(app());
    $seeder->setCommand($command);
    $seeder->run();
}

const TEST_USERS_EMAILS = [
    'juliarteaga938@gmail.com',
    'nicorfire1.4@gmail.com',
    'julian21arteaga@gmail.com',
];

test('TestUsersSeeder aborta fuera de local/testing sin crear usuarios', function () {
    app()->instance('env', 'production');

    try {
        runSeederWithCommand(new TestUsersSeeder(), makeFakeSeederCommand());
    } finally {
        app()->instance('env', 'testing');
    }

    expect(User::query()->count())->toBe(0)
        ->and(AuthorizedEmail::query()->count())->toBe(0);
});

test('DatabaseSeeder no ejecuta TestUsersSeeder fuera de local/testing', function () {
    app()->instance('env', 'production');

    try {
        runSeederWithCommand(new DatabaseSeeder(), makeFakeSeederCommand());
    } finally {
        app()->instance('env', 'testing');
    }

    expect(User::query()->whereIn('email', TEST_USERS_EMAILS)->count())->toBe(0);
});

test('TestUsersSeeder sigue funcionando en entorno testing', function () {
    runSeederWithCommand(new TestUsersSeeder(), makeFakeSeederCommand());

    expect(User::query()->whereIn('email', TEST_USERS_EMAILS)->count())->toBe(3);
});