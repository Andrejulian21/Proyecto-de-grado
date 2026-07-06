<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configured for Sanctum cookie-based SPA auth. The frontend (Vite dev
    | server in local dev, real app domain in production) sends cookies
    | with credentials, so we must:
    |   - Restrict allowed_origins to the SPA host (no wildcards).
    |   - Enable supports_credentials so the browser sends XSRF-TOKEN +
    |     laravel_session cookies.
    |   - Include sanctum/csrf-cookie in paths so the SPA can bootstrap CSRF.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('SANCTUM_CORS_ALLOWED_ORIGINS', 'http://localhost:5173')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-CSRF-TOKEN',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
