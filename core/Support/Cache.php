<?php

declare(strict_types=1);

namespace Agus\Support;

/**
 * A small file cache in storage/cache. Useful for keeping external API
 * results so the app doesn't call the API on every page view (rate limits).
 *
 *   $weather = Cache::remember('weather:angeles', 600, fn () => $api->fetch());
 */
final class Cache
{
    private static function path(string $key): string
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/' . sha1($key) . '.cache';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return $default;
        }
        $data = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if (!is_array($data) || ($data['expires'] !== 0 && $data['expires'] < time())) {
            @unlink($file);
            return $default;
        }
        return $data['value'];
    }

    /** $seconds = 0 keeps it forever. Only arrays/scalars are stored. */
    public static function put(string $key, mixed $value, int $seconds = 0): void
    {
        $payload = serialize(['expires' => $seconds > 0 ? time() + $seconds : 0, 'value' => $value]);
        file_put_contents(self::path($key), $payload, LOCK_EX);
    }

    public static function has(string $key): bool
    {
        return self::get($key, '__missing__') !== '__missing__';
    }

    public static function forget(string $key): void
    {
        @unlink(self::path($key));
    }

    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        $value = self::get($key, '__missing__');
        if ($value !== '__missing__') {
            return $value;
        }
        $value = $callback();
        if ($value !== null) {
            self::put($key, $value, $seconds);
        }
        return $value;
    }

    /** Adds 1 to a counter that resets after $seconds. Returns the new count. Used by rate limiting. */
    public static function hit(string $key, int $seconds): int
    {
        $file = self::path($key);
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return 1;
        }
        flock($handle, LOCK_EX);
        $data = @unserialize((string) stream_get_contents($handle), ['allowed_classes' => false]);
        if (!is_array($data) || $data['expires'] < time()) {
            $data = ['expires' => time() + $seconds, 'value' => 0];
        }
        $data['value']++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, serialize($data));
        flock($handle, LOCK_UN);
        fclose($handle);
        return (int) $data['value'];
    }

    public static function ttl(string $key): int
    {
        $file = self::path($key);
        $data = is_file($file) ? @unserialize((string) file_get_contents($file), ['allowed_classes' => false]) : null;
        return is_array($data) ? max(0, $data['expires'] - time()) : 0;
    }
}
