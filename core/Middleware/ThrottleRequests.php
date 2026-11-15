<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Exceptions\HttpException;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Cache;

/**
 * Rate limiting: 'throttle:5,1' = at most 5 requests per 1 minute
 * per IP address (and per route). Good for login forms and API routes.
 */
class ThrottleRequests implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        $max = (int) ($args[0] ?? 60);
        $minutes = (int) ($args[1] ?? 1);

        $key = 'throttle:' . $request->ip() . ':' . $request->method() . ':' . $request->path();
        $hits = Cache::hit($key, $minutes * 60);

        if ($hits > $max) {
            $exception = new HttpException(429);
            throw $exception;
        }

        $response = $next($request);
        $response->header('X-RateLimit-Limit', (string) $max);
        $response->header('X-RateLimit-Remaining', (string) max(0, $max - $hits));
        return $response;
    }
}
