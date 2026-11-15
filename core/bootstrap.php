<?php
/**
 * AGUS bootstrap
 * -----------------
 * Loaded by public/index.php, the CLI tool and the test runner.
 * It registers the autoloader, loads .env and helpers, and returns the App.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/*
 * PSR-4 style autoloader (no Composer needed).
 *   Agus\Http\Request    -> core/Http/Request.php
 *   App\Controllers\HomeController -> app/Controllers/HomeController.php
 */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Agus\\' => BASE_PATH . '/core/',
        'App\\'     => BASE_PATH . '/app/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $dir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
});

require BASE_PATH . '/core/Support/helpers.php';

Agus\Support\Env::load(BASE_PATH . '/.env');
Agus\Support\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) config('app.timezone', 'Asia/Manila'));

return new Agus\App(BASE_PATH);
