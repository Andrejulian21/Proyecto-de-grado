<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (includes SPA catch-all + OAuth endpoints)
|--------------------------------------------------------------------------
|
| The React SPA is served by Vite during development and from the public
| build in production. Everything that isn't `/api/*` falls through to the
| SPA so client-side routing (React Router) can take over.
|
| OAuth flow (PR 1 stub — PR 2 implements the full triple validation):
|   GET  /auth/redirect   — initiate Google OAuth
|   GET  /auth/callback   — Google redirects back here
|
*/

// Health check for uptime monitoring
Route::get('/up', fn () => response('OK', 200))->name('health');

// OAuth endpoints (controller created in PR 2 with full triple validation)
Route::get('/auth/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Serve storage files for authenticated users only. The client-provided
// path is validated against directory traversal (see StorageController):
// `..` segments are rejected and the realpath() result must stay inside
// the disk root. Unauthenticated browser requests redirect to /login.
Route::get('/storage/{path}', StorageController::class)
    ->middleware('auth:sanctum')
    ->where('path', '.*')
    ->name('storage.serve');

// Named login route: auth:sanctum redirects unauthenticated browser requests
// to protected routes (e.g. /storage/*) here, rendering the SPA login page.
Route::get('/login', fn () => view('app'))->name('login');

// SPA catch-all — anything that is not /api/* or an explicit route above
// serves the Vite/React index.html so client-side routing works.
// Static assets are served by the web server before reaching here.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|storage).*$')->name('spa');
