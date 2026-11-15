<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Logger;

/**
 * Writes one log line per request:
 *   [2026-11-14 09:12:03] INFO: GET /listings 200 {"ms":12.4,"ip":"127.0.0.1","user":3}
 */
class LogRequests implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        $start = microtime(true);
        $response = $next($request);

        if (config('app.log_requests', true)) {
            Logger::info(sprintf('%s %s %d', $request->method(), $request->path(), $response->status()), [
                'ms' => round((microtime(true) - $start) * 1000, 1),
                'ip' => $request->ip(),
                'user' => auth()['id'] ?? null,
            ]);
        }

        // Handy while debugging: shows the time in the browser's Network tab.
        $response->header('X-Response-Time', round((microtime(true) - $start) * 1000, 1) . 'ms');
        return $response;
    }
}
