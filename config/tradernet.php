<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | Name of the connection used when resolving Tradernet\Sdk\Tradernet
    | from the container or via the Tradernet facade.
    |
    */

    'default' => env('TRADERNET_CONNECTION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each connection maps to one Tradernet cabinet (domain + API keys).
    | Named connections support demo vs live, multi-region cabinets, etc.
    |
    */

    'connections' => [
        'main' => [
            'api_key' => env('TRADERNET_API_KEY'),
            'api_secret' => env('TRADERNET_API_SECRET'),
            'login' => env('TRADERNET_LOGIN'),
            'password' => env('TRADERNET_PASSWORD'),

            /*
            | Optional invokable class name resolved from the container and
            | called lazily when the SDK needs a password. Prefer this over
            | storing passwords in env when integrating a secrets manager.
            | Must not be a Closure — config:cache requires serializable config.
            */
            'password_resolver' => null,

            'domain' => env('TRADERNET_DOMAIN', 'https://tradernet.com'),
            'lang' => env('TRADERNET_LANG', 'en'),
            'auth_mode' => env('TRADERNET_AUTH_MODE', 'sid_lazy'),
            'timeout' => (float) env('TRADERNET_TIMEOUT', 30),
            'user_agent' => env('TRADERNET_USER_AGENT'),
            'sid_cookie' => env('TRADERNET_SID_COOKIE', 'SID'),
            'sid_ttl' => (int) env('TRADERNET_SID_TTL', 1_209_600),

            'reauth' => [
                'max_attempts' => (int) env('TRADERNET_REAUTH_MAX_ATTEMPTS', 3),
                'window_seconds' => (int) env('TRADERNET_REAUTH_WINDOW', 900),
                'open_seconds' => (int) env('TRADERNET_REAUTH_OPEN', 900),
            ],

            'session' => [
                /*
                | cache   — Laravel cache store (recommended shared Redis)
                | memory  — process-local (tests / single worker)
                | null    — no persistence
                */
                'driver' => env('TRADERNET_SESSION_DRIVER', 'cache'),

                /*
                | Cache store name from config/cache.php. Prefer a dedicated
                | store so php artisan cache:clear on the default store does
                | not wipe SID sessions (ReauthGuard may block ~15 minutes).
                */
                'store' => env('TRADERNET_SESSION_STORE'),

                'prefix' => env('TRADERNET_SESSION_PREFIX', 'tradernet:sid:'),
                // FILTER_NULL_ON_FAILURE so a typo cannot silently disable encryption.
                'encrypt' => filter_var(env('TRADERNET_SESSION_ENCRYPT', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
                'lock_ttl' => (int) env('TRADERNET_LOCK_TTL', 90),
                'lock_wait' => (int) env('TRADERNET_LOCK_WAIT', 20),
                'meta_ttl' => (int) env('TRADERNET_META_TTL', 3600),
            ],
        ],
    ],
];
