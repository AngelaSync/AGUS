<?php

declare(strict_types=1);

namespace Agus;

use Agus\Exceptions\HttpException;
use Agus\Exceptions\ValidationException;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Middleware\Pipeline;
use Agus\Routing\Route;
use Agus\Routing\Router;
use Agus\Support\Logger;
use Agus\View\View;

/**
 * The application kernel. The whole request lifecycle lives in handle():
 *
 *   Request::capture()
 *     -> Router finds the route
 *     -> global + route middleware run (Pipeline)
 *     -> controller action runs and returns a Response
 *     -> errors become error pages / JSON
 *   Response::send()
 */
class App
{
    private static ?App $instance = null;
    private Router $router;

    public function __construct(private string $basePath)
    {
        self::$instance = $this;
        $this->router = new Router();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('AGUS has not been bootstrapped.');
        }
        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function router(): Router
    {
        return $this->router;
    }

    /** Loads routes/web.php and routes/api.php (if present). */
    public function loadRoutes(): self
    {
        $router = $this->router;
        foreach (['web.php', 'api.php'] as $file) {
            $path = $this->basePath('routes/' . $file);
            if (is_file($path)) {
                require $path;
            }
        }
        return $this;
    }

    public function run(): void
    {
        $this->loadRoutes();
        $this->handle(Request::capture())->send();
    }

    public function handle(Request $request): Response
    {
        $GLOBALS['__agus_request'] = $request;

        try {
            $global = (array) config('app.middleware.global', []);
            $aliases = (array) config('app.middleware.aliases', []);

            // Global middleware runs even when no route matches, so 404 pages still get a session.
            return (new Pipeline($global, $aliases))->then($request, function (Request $request) use ($aliases): Response {
                try {
                    [$route, $params] = $this->router->resolve($request);
                    $request->setParams($params);
                    $request->setAttribute('route', $route);

                    return (new Pipeline($route->getMiddleware(), $aliases))
                        ->then($request, fn (Request $r): Response => $this->callAction($route, $r));
                } catch (\Throwable $e) {
                    // Errors still travel back out through the global middleware (logging, headers).
                    return $this->renderException($e, $request);
                }
            });
        } catch (\Throwable $e) {
            return $this->renderException($e, $request);
        }
    }

    /** Creates the controller and calls the action, injecting Request and route params. */
    private function callAction(Route $route, Request $request): Response
    {
        $handler = $route->getHandler();

        try {
            if (is_array($handler) && is_string($handler[0])) {
                [$class, $method] = $handler;
                if (!class_exists($class)) {
                    throw new \RuntimeException("Controller [$class] not found.");
                }
                $controller = new $class();
                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException("Method [$class::$method] not found.");
                }
                $callable = [$controller, $method];
                $reflection = new \ReflectionMethod($controller, $method);
            } elseif (is_callable($handler)) {
                $callable = $handler;
                $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
            } else {
                throw new \RuntimeException('Invalid route handler for ' . $route->getPattern());
            }

            $args = $this->resolveArguments($reflection, $request);
            $result = $callable(...$args);
            return $this->toResponse($result);
        } catch (\Throwable $e) {
            return $this->renderException($e, $request);
        }
    }

    /**
     * Matches parameters by type or name:
     *   show(Request $request, int $id)  <- route /listings/{id}
     */
    private function resolveArguments(\ReflectionFunctionAbstract $fn, Request $request): array
    {
        $args = [];
        foreach ($fn->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[] = $request;
                continue;
            }

            $value = $request->param($param->getName());
            if ($value !== null) {
                $args[] = match ($typeName) {
                    'int' => (int) $value,
                    'float' => (float) $value,
                    'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
                    default => $value,
                };
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new \RuntimeException('Cannot resolve parameter $' . $param->getName());
            }
        }
        return $args;
    }

    /** Lets controllers return a Response, a string (HTML) or an array (JSON). */
    private function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_array($result), $result instanceof \JsonSerializable => Response::json($result),
            $result === null => new Response('', 204),
            default => Response::html((string) $result),
        };
    }

    public function renderException(\Throwable $e, Request $request): Response
    {
        if ($e instanceof ValidationException) {
            if ($request->wantsJson()) {
                return Response::json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return Response::back()->withErrors($e->errors())->withInput($e->input());
        }

        $status = $e instanceof HttpException ? $e->status() : 500;
        $debug = (bool) config('app.debug', false);

        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->method() . ' ' . $request->path(),
            ]);
        }

        $message = $status >= 500 && !$debug ? HttpException::defaultMessage(500) : $e->getMessage();

        if ($request->wantsJson()) {
            $payload = ['message' => $message];
            if ($debug && $status >= 500) {
                $payload['exception'] = $e::class;
                $payload['file'] = $e->getFile() . ':' . $e->getLine();
            }
            return Response::json($payload, $status);
        }

        try {
            $html = View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'exception' => $debug && $status >= 500 ? $e : null,
            ]);
        } catch (\Throwable) {
            $html = '<h1>' . $status . '</h1><p>' . htmlspecialchars($message) . '</p>';
        }
        return Response::html($html, $status);
    }
}
