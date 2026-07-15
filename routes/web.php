<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
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

// SPA entry points — named routes required by Laravel auth middleware (API 401 fallback).
Route::get('/login', fn () => view('app'))->name('login');
Route::get('/login/externo', fn () => view('app'))->name('login.externo');

// SPA catch-all — anything that is not /api/* or an explicit route above
// serves the Vite/React index.html so client-side routing works.
// Static assets are served by the web server before reaching here.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$')->name('spa');
