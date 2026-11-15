<?php

declare(strict_types=1);

namespace Agus\Support;

/**
 * Thin wrapper over $_SESSION with "flash" values that live for exactly one
 * more request (success messages, form errors, old input).
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        $path = (string) config('app.session.path', BASE_PATH . '/storage/sessions');
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        session_save_path($path);
        session_name((string) config('app.session.name', 'agus_session'));
        session_set_cookie_params([
            'lifetime' => (int) config('app.session.lifetime', 0),
            'path' => '/',
            'httponly' => true,                                    // JavaScript can't read it
            'samesite' => 'Lax',                                   // basic CSRF protection
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }

    /** Moves last request's flash data out of the way so it is shown only once. */
    public static function ageFlashData(): void
    {
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /** Available on the next request only. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    /** Reads a flash value set on the previous request (or earlier in this one). */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_new'][$key] ?? $_SESSION['_flash_old'][$key] ?? $default;
    }

    /** New session id after login/logout so an old stolen id stops working. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function token(): string
    {
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }
}
