<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserController;
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
});

// -- admin (coordinador-only) routes ---------------------------------

Route::middleware(['auth:sanctum', 'role:Coordinador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/evaluadores', [UserController::class, 'storeExternal'])
            ->name('evaluadores.store');
    });
