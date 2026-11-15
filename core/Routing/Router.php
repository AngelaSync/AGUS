<?php

declare(strict_types=1);

namespace Agus\Routing;

use Agus\Exceptions\HttpException;
use Agus\Http\Request;

/**
 * Stores every route from routes/web.php and finds the one that matches a request.
 *
 *   $router->get('/listings/{id:\d+}', [ListingController::class, 'show'])->name('listings.show');
 *   $router->group(['prefix' => '/admin', 'middleware' => ['auth']], function (Router $r) { ... });
 */
class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Route> */
    private static array $named = [];

    /** Prefix/middleware stack for nested groups. */
    private array $groupStack = [];

    public function get(string $path, mixed $handler): Route
    {
        return $this->add(['GET'], $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->add(['POST'], $path, $handler);
    }

    public function put(string $path, mixed $handler): Route
    {
        return $this->add(['PUT'], $path, $handler);
    }

    public function patch(string $path, mixed $handler): Route
    {
        return $this->add(['PATCH'], $path, $handler);
    }

    public function delete(string $path, mixed $handler): Route
    {
        return $this->add(['DELETE'], $path, $handler);
    }

    public function any(string $path, mixed $handler): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler);
    }

    public function match(array $methods, string $path, mixed $handler): Route
    {
        return $this->add(array_map('strtoupper', $methods), $path, $handler);
    }

    /**
     * Registers the 7 usual CRUD routes for a resource controller:
     * index, create, store, show, edit, update, destroy.
     */
    public function resource(string $path, string $controller, string $name = ''): void
    {
        $path = '/' . trim($path, '/');
        $name = $name !== '' ? $name : trim(str_replace('/', '.', $path), '.');

        $this->get($path, [$controller, 'index'])->name("$name.index");
        $this->get("$path/create", [$controller, 'create'])->name("$name.create");
        $this->post($path, [$controller, 'store'])->name("$name.store");
        $this->get("$path/{id:\d+}", [$controller, 'show'])->name("$name.show");
        $this->get("$path/{id:\d+}/edit", [$controller, 'edit'])->name("$name.edit");
        $this->put("$path/{id:\d+}", [$controller, 'update'])->name("$name.update");
        $this->delete("$path/{id:\d+}", [$controller, 'destroy'])->name("$name.destroy");
    }

    /** Shares a URL prefix and/or middleware with every route inside $routes. */
    public function group(array $attributes, callable $routes): void
    {
        $this->groupStack[] = [
            'prefix' => '/' . trim((string) ($attributes['prefix'] ?? ''), '/'),
            'middleware' => (array) ($attributes['middleware'] ?? []),
        ];
        $routes($this);
        array_pop($this->groupStack);
    }

    private function add(array $methods, string $path, mixed $handler): Route
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] === '/' ? '' : $group['prefix'];
            $middleware = array_merge($middleware, $group['middleware']);
        }

        $route = new Route($methods, $prefix . '/' . ltrim($path, '/'), $handler);
        if ($middleware) {
            $route->middleware($middleware);
        }
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Finds the route for the request.
     * 404 if no URL matches, 405 if the URL exists but not for this method.
     *
     * @return array{0: Route, 1: array<string, string>}
     */
    public function resolve(Request $request): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $route->match($request->path());
            if ($params === null) {
                continue;
            }
            if ($route->allows($request->method())) {
                return [$route, $params];
            }
            $allowed = array_merge($allowed, $route->getMethods());
        }

        if ($allowed) {
            throw new HttpException(405, 'Method not allowed. Use ' . implode(', ', array_unique($allowed)) . '.');
        }
        throw new HttpException(404);
    }

    public static function registerName(string $name, Route $route): void
    {
        self::$named[$name] = $route;
    }

    public static function urlFor(string $name, array $params = []): string
    {
        if (!isset(self::$named[$name])) {
            throw new \InvalidArgumentException("No route named [$name].");
        }
        return self::$named[$name]->url($params);
    }

    /** @return Route[] */
    public function routes(): array
    {
        return $this->routes;
    }
}
