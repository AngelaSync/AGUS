<?php

declare(strict_types=1);

namespace Agus\Routing;

/**
 * One route: an HTTP method + URL pattern + handler (+ optional middleware/name).
 *
 *   /listings/{id}          {id} matches one URL segment
 *   /listings/{id:\d+}      {id} must be digits
 *   /blog/{slug?}           optional last segment
 */
class Route
{
    private ?string $name = null;
    private array $middleware = [];
    private string $regex;
    private array $paramNames = [];

    /** @param callable|array{0: class-string, 1: string}|string $handler */
    public function __construct(
        private array $methods,
        private string $pattern,
        private mixed $handler,
    ) {
        $this->pattern = '/' . trim($pattern, '/');
        $this->compile();
    }

    private function compile(): void
    {
        $this->paramNames = [];
        $regex = preg_replace_callback(
            '#/?\{(\w+)(\?)?(?::([^}]+))?\}#',
            function (array $m): string {
                $this->paramNames[] = $m[1];
                $constraint = ($m[3] ?? '') !== '' ? $m[3] : '[^/]+';
                $optional = ($m[2] ?? '') === '?';
                return $optional ? '(?:/(' . $constraint . '))?' : '/(' . $constraint . ')';
            },
            $this->pattern
        );
        $this->regex = '#^' . ($regex === '' ? '/' : $regex) . '$#';
    }

    /** Returns the route params if the path matches, otherwise null. */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }
        $params = [];
        foreach ($this->paramNames as $i => $name) {
            $value = $matches[$i + 1] ?? null;
            if ($value !== null && $value !== '') {
                $params[$name] = $value;
            }
        }
        return $params;
    }

    public function allows(string $method): bool
    {
        return in_array($method, $this->methods, true)
            || ($method === 'HEAD' && in_array('GET', $this->methods, true));
    }

    public function name(string $name): self
    {
        $this->name = $name;
        Router::registerName($name, $this);
        return $this;
    }

    /** Add middleware to just this route: ->middleware('auth') */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    /** Builds a URL for this route, e.g. route('listings.show', ['id' => 5]). */
    public function url(array $params = []): string
    {
        $path = preg_replace_callback(
            '#\{(\w+)\??(?::[^}]+)?\}#',
            function (array $m) use (&$params): string {
                $value = $params[$m[1]] ?? '';
                unset($params[$m[1]]);
                return rawurlencode((string) $value);
            },
            $this->pattern
        );
        $path = '/' . trim(preg_replace('#/+#', '/', (string) $path), '/');
        return $params ? $path . '?' . http_build_query($params) : $path;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function handlerName(): string
    {
        $h = $this->handler;
        if (is_array($h)) {
            return (is_object($h[0]) ? $h[0]::class : $h[0]) . '@' . $h[1];
        }
        if (is_string($h)) {
            return $h;
        }
        return 'Closure';
    }
}
