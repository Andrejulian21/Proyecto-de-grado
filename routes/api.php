<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with `/api` and run through the
| `api` middleware group (throttle, substitute bindings, etc).
|
| For the auth module (PR 1 — Foundation), only the OAuth redirect/callback
| stubs and a health endpoint exist. PR 2 will add loginExterno, logout,
| sessionCheck, UserController, and AuditLogController.
|
*/

Route::get('/health', fn () => ['status' => 'ok', 'time' => now()->toIso8601String()]);
