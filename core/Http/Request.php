<?php

declare(strict_types=1);

namespace Agus\Http;

/**
 * Wraps one incoming HTTP request (query string, form/JSON body, headers,
 * files, cookies) so controllers never touch $_GET / $_POST directly.
 */
class Request
{
    /** Values that middleware attaches for later use, e.g. the signed-in user. */
    private array $attributes = [];

    /** Route parameters such as {id}, filled in by the Router. */
    private array $params = [];

    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private array $headers = [],
        private array $files = [],
        private array $cookies = [],
        private array $server = [],
        private string $rawBody = '',
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
    }

    /** Builds a Request from PHP's superglobals. */
    public static function capture(): self
    {
        $server = $_SERVER;
        $uriPath = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uriPath = rawurldecode($uriPath);

        // Works whether the app lives at the domain root or in a subfolder
        // such as http://localhost/agus/ (XAMPP).
        $base = self::detectBasePath($server, $uriPath);
        $path = $base !== '' && str_starts_with($uriPath, $base) ? substr($uriPath, strlen($base)) : $uriPath;

        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        $raw = (string) file_get_contents('php://input');
        $body = $_POST;
        if (str_contains($headers['content-type'] ?? '', 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        // HTML forms can only send GET/POST, so allow <input name="_method" value="DELETE">.
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && isset($body['_method'])) {
            $spoofed = strtoupper((string) $body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoofed;
            }
        }

        $request = new self($method, $path, $_GET, $body, $headers, $_FILES, $_COOKIE, $server, $raw);
        $request->setAttribute('base_path', $base);
        return $request;
    }

    private static function detectBasePath(array $server, string $uriPath): string
    {
        $script = str_replace('\\', '/', dirname($server['SCRIPT_NAME'] ?? '/'));
        $script = rtrim($script, '/');

        foreach ([$script, preg_replace('#/public$#', '', $script)] as $candidate) {
            if ($candidate !== '' && $candidate !== null && ($uriPath === $candidate || str_starts_with($uriPath, $candidate . '/'))) {
                return $candidate;
            }
        }
        return '';
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    // ---- basic info -------------------------------------------------------

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function userAgent(): string
    {
        return (string) $this->header('user-agent', '');
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function isJson(): bool
    {
        return str_contains((string) $this->header('content-type', ''), 'application/json');
    }

    /** True for fetch()/AJAX calls and anything under /api. */
    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('accept', ''), 'application/json')
            || $this->header('x-requested-with') === 'XMLHttpRequest'
            || str_starts_with($this->path, '/api/') || $this->path === '/api';
    }

    // ---- input ------------------------------------------------------------

    /** Reads from the body first, then the query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK ? $file : null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    // ---- route params and attributes --------------------------------------

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** Shortcut for the user that AuthMiddleware attached. */
    public function user(): mixed
    {
        return $this->attributes['user'] ?? null;
    }
}
