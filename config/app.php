<?php
/**
 * Main application settings. Values come from .env where possible so the
 * same code runs on your laptop (XAMPP) and on a server without edits.
 */

return [
    'name' => env('APP_NAME', 'AGUS'),

    // local | production
    'env' => env('APP_ENV', 'local'),

    // true shows full error details. Always false on a live server.
    'debug' => env('APP_DEBUG', false),

    // Leave empty to detect automatically (works in XAMPP subfolders).
    // Set it when the app is behind a proxy, e.g. https://replate.example.com
    'url' => env('APP_URL', ''),

    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    'currency' => env('APP_CURRENCY', '₱'),

    'log_requests' => env('LOG_REQUESTS', true),

    'session' => [
        'name' => env('SESSION_NAME', 'agus_session'),
        'lifetime' => 0, // 0 = until the browser closes
        'path' => BASE_PATH . '/storage/sessions',
    ],

    'auth' => [
        'table' => 'users',
        'login_path' => '/login',
        'home_path' => '/dashboard',
    ],

    // Paths that skip CSRF checks (token-based APIs, webhooks).
    'csrf_except' => [
        '/api/*',
    ],

    'middleware' => [
        // Runs on every request, in this order.
        'global' => [
            Agus\Middleware\LogRequests::class,
            Agus\Middleware\SecurityHeaders::class,
            Agus\Middleware\StartSession::class,
            Agus\Middleware\VerifyCsrfToken::class,
        ],

        // Short names to use in routes: ->middleware('auth'), 'throttle:5,1', 'role:admin'
        'aliases' => [
            'auth' => App\Middleware\AuthMiddleware::class,
            'guest' => App\Middleware\GuestMiddleware::class,
            'role' => App\Middleware\RoleMiddleware::class,
            'throttle' => Agus\Middleware\ThrottleRequests::class,
        ],
    ],
];
