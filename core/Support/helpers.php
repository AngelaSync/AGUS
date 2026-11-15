<?php
/**
 * Global helper functions available everywhere (controllers, views, config).
 */

declare(strict_types=1);

use Agus\Exceptions\HttpException;
use Agus\Http\Request;
use Agus\Routing\Router;
use Agus\Support\Auth;
use Agus\Support\Config;
use Agus\Support\Env;
use Agus\Support\Session;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('e')) {
    /** Escape output for HTML. Use it for every value you print: <?= e($name) ?> */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('request')) {
    function request(): ?Request
    {
        return $GLOBALS['__agus_request'] ?? null;
    }
}

if (!function_exists('url')) {
    /**
     * Full URL for a path inside the app. Uses APP_URL when set, otherwise
     * works out the folder the app is served from (e.g. /agus in XAMPP).
     */
    function url(string $path = '/'): string
    {
        $root = rtrim((string) config('app.url', ''), '/');
        if ($root === '') {
            $root = rtrim((string) (request()?->attribute('base_path') ?? ''), '/');
        }
        return $root . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** URL for a file in public/assets: asset('css/app.css') */
    function asset(string $path): string
    {
        // The root .htaccess sends /assets/... to public/assets/..., so this works
        // with `php agus serve` and inside an XAMPP subfolder alike.
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('route')) {
    /** URL for a named route: route('listings.show', ['id' => 5]) */
    function route(string $name, array $params = []): string
    {
        return url(Router::urlFor($name, $params));
    }
}

if (!function_exists('is_active')) {
    /** For nav links: class="<?= is_active('/listings') ?>" */
    function is_active(string $path, string $class = 'active'): string
    {
        $current = request()?->path() ?? '/';
        $path = '/' . trim($path, '/');
        return ($path === '/' ? $current === '/' : str_starts_with($current, $path)) ? $class : '';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::token();
    }
}

if (!function_exists('csrf_field')) {
    /** Put inside every POST form: <?= csrf_field() ?> */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    /** For PUT/PATCH/DELETE forms: <?= method_field('DELETE') ?> */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /** Refill a form after a validation error: value="<?= e(old('email')) ?>" */
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::getFlash('_old_input', [])[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /** All validation errors, or the first error for one field: errors('email') */
    function errors(?string $field = null): mixed
    {
        $errors = Session::getFlash('_errors', []);
        return $field === null ? $errors : ($errors[$field][0] ?? null);
    }
}

if (!function_exists('flash')) {
    /** Read a one-time message: flash('success') */
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}

if (!function_exists('auth')) {
    /** The signed-in user as an array, or null. */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}

if (!function_exists('money')) {
    /** money(25) -> ₱25.00 (currency symbol from config/app.php) */
    function money(int|float|string|null $amount, int $decimals = 2): string
    {
        return config('app.currency', '₱') . number_format((float) $amount, $decimals);
    }
}

if (!function_exists('dd')) {
    /** Dump values and stop. For debugging only. */
    function dd(mixed ...$values): never
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo PHP_SAPI === 'cli' ? '' : '<pre style="background:#1c130f;color:#f6efe3;padding:16px;border-radius:8px;overflow:auto">';
        foreach ($values as $value) {
            echo PHP_SAPI === 'cli' ? print_r($value, true) . PHP_EOL : e(print_r($value, true)) . "\n";
        }
        echo PHP_SAPI === 'cli' ? '' : '</pre>';
        exit(1);
    }
}
