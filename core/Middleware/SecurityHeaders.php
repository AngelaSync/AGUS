<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;

/** Adds standard browser security headers to every response. */
class SecurityHeaders implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        $response = $next($request);
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        return $response;
    }
}
