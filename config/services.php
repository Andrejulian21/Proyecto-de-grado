<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Laravel Socialite) — institutional login for @unab.edu.co
    |--------------------------------------------------------------------------
    |
    | Create the OAuth 2.0 client at
    | https://console.cloud.google.com/apis/credentials
    |
    | Authorized redirect URI must match exactly: ${APP_URL}/auth/callback
    |
    | The `hd` parameter is sent on the redirect so Google returns the hosted
    | domain in the id_token; we still triple-validate server-side (see
    | AuthController@handleGoogleCallback).
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'hd' => env('GOOGLE_ALLOWED_HOSTED_DOMAIN', 'unab.edu.co'),
        'scopes' => [
            'openid',
            'profile',
            'email',
        ],
        'access_type' => 'online',
        'prompt' => 'select_account',
    ],

];
