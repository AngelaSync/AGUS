<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;

/**
 * Runs a request through a list of middleware like layers of an onion,
 * then calls the final destination (the controller).
 *
 *   Log -> Session -> Csrf -> Auth -> Controller -> Auth -> Csrf -> Session -> Log
 */
class Pipeline
{
    /**
     * @param string[] $middleware Aliases ('auth'), aliases with args ('throttle:5,1') or class names.
     * @param array<string, class-string> $aliases
     */
    public function __construct(private array $middleware, private array $aliases = [])
    {
    }

    public function then(Request $request, callable $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($this->middleware) as $entry) {
            [$class, $args] = $this->parse($entry);
            $next = function (Request $request) use ($class, $args, $next): Response {
                $instance = new $class();
                if (!$instance instanceof Middleware) {
                    throw new \LogicException("$class must implement " . Middleware::class);
                }
                return $instance->handle($request, $next, ...$args);
            };
        }

        return $next($request);
    }

    /** 'throttle:60,1' -> [ThrottleMiddleware::class, ['60', '1']] */
    private function parse(string $entry): array
    {
        [$name, $argString] = array_pad(explode(':', $entry, 2), 2, '');
        $class = $this->aliases[$name] ?? $name;
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Unknown middleware [$name]. Add it to 'middleware.aliases' in config/app.php.");
        }
        $args = $argString === '' ? [] : array_map('trim', explode(',', $argString));
        return [$class, $args];
    }
}
