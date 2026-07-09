<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\EntregaController;
use App\Http\Controllers\Admin\ProyectoController;
use App\Http\Controllers\Admin\SemestreController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with `/api` and run through the `api`
| middleware group (Sanctum stateful, throttle, etc).
|
| PR 2 — Auth Backend (T-014 to T-025):
|   - POST   /api/auth/externo/login      → loginExterno        (T-016)
|   - POST   /api/auth/change-password    → changePassword      (T-017)
|   - POST   /api/auth/logout             → logout              (T-023)
|   - GET    /api/auth/user               → sessionCheck        (T-023)
|   - GET    /api/admin/audit-logs        → AuditLogController  (T-024)
|   - CRUD   /api/admin/whitelist         → UserController      (T-020)
|   - POST   /api/admin/evaluadores       → UserController      (T-017)
|
*/

// Health check (used by `/up` and uptime monitors).
Route::get('/health', fn () => ['status' => 'ok', 'time' => now()->toIso8601String()]);

// -- guest routes ----------------------------------------------------

Route::post('/auth/externo/login', [AuthController::class, 'loginExterno'])
    ->name('auth.externo.login');

// -- authenticated routes -------------------------------------------

// Authenticated API routes are guarded by:
//   - auth:sanctum           (Sanctum cookie/bearer token)
//   - single_session         (T-021, belt-and-suspenders)
//   - activity               (T-022, 8h inactivity timeout)
//   - ensure_password_changed (T-017, forces external evaluators to
//     change their temporary password)
Route::middleware([
    'auth:sanctum',
    'single_session',
    'activity',
    'ensure_password_changed',
])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'sessionCheck'])
        ->name('auth.user');

    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])
        ->name('auth.change_password');

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');

    // Bitácoras CRUD + firma (T-012)
    Route::get('/bitacoras', [\App\Http\Controllers\Api\BitacoraController::class, 'index'])
        ->name('bitacoras.index');
    Route::post('/bitacoras', [\App\Http\Controllers\Api\BitacoraController::class, 'store'])
        ->name('bitacoras.store');
    Route::get('/bitacoras/{id}', [\App\Http\Controllers\Api\BitacoraController::class, 'show'])
        ->whereNumber('id')
        ->name('bitacoras.show');
    Route::put('/bitacoras/{id}', [\App\Http\Controllers\Api\BitacoraController::class, 'update'])
        ->whereNumber('id')
        ->name('bitacoras.update');
    Route::post('/bitacoras/{id}/firmar', [\App\Http\Controllers\Api\BitacoraController::class, 'firmar'])
        ->whereNumber('id')
        ->name('bitacoras.firmar');

    // T-014: Total bitácora hours per project (director/coordinador)
    Route::get('/director/proyectos/{id}/horas', [\App\Http\Controllers\Api\BitacoraController::class, 'horas'])
        ->whereNumber('id')
        ->name('director.proyectos.horas');

    // Entregas — versiones (accessible by authenticated students and directors)
    Route::get('/entregas/{id}/versiones', [EntregaController::class, 'versiones'])
        ->whereNumber('id')
        ->name('entregas.versiones');
    Route::post('/entregas/{id}/versiones', [EntregaController::class, 'subirVersion'])
        ->whereNumber('id')
        ->name('entregas.subir_version');

    // Estudiante solicita habilitación para subir versiones
    Route::post('/entregas/{id}/solicitar', [EntregaController::class, 'solicitar'])
        ->whereNumber('id')
        ->name('entregas.solicitar');
});

// -- admin (coordinador-only) routes ---------------------------------

Route::middleware(['auth:sanctum', 'role:Coordinador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/usuarios', [UserController::class, 'usuarios'])
            ->name('usuarios.index');
        Route::put('/usuarios/{id}', [UserController::class, 'updateUsuario'])
            ->whereNumber('id')
            ->name('usuarios.update');
        Route::delete('/usuarios/{id}', [UserController::class, 'destroyUsuario'])
            ->whereNumber('id')
            ->name('usuarios.destroy');

        Route::post('/evaluadores', [UserController::class, 'storeExternal'])
            ->name('evaluadores.store');

        // Whitelist CRUD (T-020).
        Route::get('/whitelist', [UserController::class, 'index'])
            ->name('whitelist.index');
        Route::post('/whitelist', [UserController::class, 'store'])
            ->name('whitelist.store');
        Route::put('/whitelist/{id}', [UserController::class, 'update'])
            ->whereNumber('id')
            ->name('whitelist.update');
        Route::delete('/whitelist/{id}', [UserController::class, 'destroy'])
            ->whereNumber('id')
            ->name('whitelist.destroy');

        // Audit log viewer (T-024).
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');

        // Proyectos KPIs (T-006). Must be before apiResource to avoid wildcard collision.
        Route::get('/proyectos/kpis', [ProyectoController::class, 'kpis'])
            ->name('proyectos.kpis');

        // Proyectos CRUD (T-002).
        Route::apiResource('proyectos', ProyectoController::class)
            ->only(['index', 'store']);

        // Semestres CRUD (T-001).
        Route::apiResource('semestres', SemestreController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

// Entregas — accessible by all authenticated roles (controller handles RBAC)
Route::middleware(['auth:sanctum', 'single_session', 'activity'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/entregas/finales', [EntregaController::class, 'finales'])
            ->name('entregas.finales');
        Route::get('/entregas', [EntregaController::class, 'index'])
            ->name('entregas.index');
        Route::post('/entregas', [EntregaController::class, 'store'])
            ->name('entregas.store');
        Route::put('/entregas/{id}/revisar', [EntregaController::class, 'revisar'])
            ->whereNumber('id')
            ->name('entregas.revisar');
        Route::put('/entregas/{id}/habilitar', [EntregaController::class, 'habilitar'])
            ->whereNumber('id')
            ->name('entregas.habilitar');
    });
