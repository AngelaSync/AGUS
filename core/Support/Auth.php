<?php

declare(strict_types=1);

namespace Agus\Support;

use Agus\Database\Database;

/**
 * Session-based login. Passwords are stored with password_hash() (bcrypt),
 * never as plain text.
 *
 *   Auth::attempt($email, $password)   // true/false, logs in on success
 *   Auth::user()                       // array|null
 *   Auth::logout()
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function attempt(string $email, string $password): bool
    {
        $table = (string) config('app.auth.table', 'users');
        $user = Database::table($table)->where('email', strtolower(trim($email)))->first();

        // Always run password_verify so a missing email takes the same time as a wrong password.
        $hash = $user['password'] ?? password_hash('timing-dummy', PASSWORD_DEFAULT);
        if (!$user || !password_verify($password, $hash)) {
            return false;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Database::table($table)->where('id', $user['id'])->update(['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put('auth_id', (int) $user['id']);
        unset($user['password']);
        self::$user = $user;
        self::$loaded = true;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('auth_id');
            if ($id) {
                $row = Database::table((string) config('app.auth.table', 'users'))->find((int) $id);
                if ($row) {
                    unset($row['password']);
                    self::$user = $row;
                } else {
                    Session::forget('auth_id'); // user was deleted
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        Session::forget('auth_id');
        Session::regenerate();
        self::$user = null;
        self::$loaded = true;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** Clears the per-request cache (used by tests). */
    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }
}
