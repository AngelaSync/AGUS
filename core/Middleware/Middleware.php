<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;

/**
 * A middleware sits between the request and the controller.
 * It can stop the request early (return a Response) or pass it on with $next($request).
 *
 *   public function handle(Request $request, callable $next, string ...$args): Response
 *   {
 *       if (!$request->user()) return Response::redirect('/login');   // stop here
 *       return $next($request);                                         // continue
 *   }
 *
 * $args come from the route definition: 'throttle:60,1' -> ['60', '1'].
 */
interface Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response;
}
