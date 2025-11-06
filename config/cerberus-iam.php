<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cerberus IAM Base URL
    |--------------------------------------------------------------------------
    |
    | The root URL for the Cerberus IAM API (e.g. https://api.cerberus-iam.local).
    | All HTTP calls raised by the client are built on top of this base.
    |
    */

    'base_url' => env('CERBERUS_IAM_URL', 'http://localhost:4000'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | If your Laravel app shares the same parent domain as the IAM platform
    | you can rely on the Cerberus session cookie to identify authenticated
    | users. Provide the cookie name here (defaults to cerb_sid).
    |
    */

    'session_cookie' => env('CERBERUS_IAM_SESSION_COOKIE', 'cerb_sid'),

    /*
    |--------------------------------------------------------------------------
    | Default Organisation Slug
    |--------------------------------------------------------------------------
    |
    | When the package needs to call administrative endpoints (e.g. fetching
    | a user by ID) it must scope the request to an organisation. Set the
    | slug of your primary organisation here.
    |
    */

    'organisation_slug' => env('CERBERUS_IAM_ORG_SLUG'),

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Client Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used when exchanging authorization codes and
    | refresh tokens against the IAM token endpoint. For public clients the
    | secret can be null, but confidential clients should always provide it.
    |
    */

    'oauth' => [
        'client_id' => env('CERBERUS_IAM_CLIENT_ID'),
        'client_secret' => env('CERBERUS_IAM_CLIENT_SECRET'),
        'redirect_uri' => env('CERBERUS_IAM_REDIRECT_URI'),
        'scopes' => explode(' ', env('CERBERUS_IAM_SCOPES', 'openid profile email')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | Customize HTTP client behaviour. The package uses jerome/fetch-php under
    | the hood which ultimately proxies to Guzzle, so the keys map directly.
    |
    */

    'http' => [
        'timeout' => env('CERBERUS_IAM_HTTP_TIMEOUT', 10),
        'retry' => [
            'enabled' => env('CERBERUS_IAM_HTTP_RETRY', true),
            'max_attempts' => env('CERBERUS_IAM_HTTP_RETRY_ATTEMPTS', 2),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    |
    | The default authentication guard name to use when handling OAuth callbacks.
    | This should match a guard name you have configured in config/auth.php
    | that uses driver: 'cerberus'.
    |
    | Example in config/auth.php:
    |   'guards' => [
    |       'web' => [
    |           'driver' => 'cerberus',
    |           'provider' => 'users',
    |       ],
    |   ]
    |
    | In the above example, you would set this to 'web', not 'cerberus'.
    | Note: 'cerberus' is the driver name, not necessarily your guard name.
    |
    */

    'default_guard' => env('CERBERUS_IAM_DEFAULT_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Post-login Redirect
    |--------------------------------------------------------------------------
    |
    | When the built-in callback route resolves a Cerberus login it will
    | redirect users to this URI (or to the intended destination stored in
    | the session, if present).
    |
    */

    'redirect_after_login' => env('CERBERUS_IAM_REDIRECT_AFTER_LOGIN', '/'),
];
