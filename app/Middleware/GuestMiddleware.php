<?php

declare(strict_types=1);

namespace App\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Middleware\Middleware;
use Agus\Support\Auth;

/** Signed-in users don't need the login/register pages, so send them to their dashboard. */
class GuestMiddleware implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        if (Auth::check()) {
            return Response::redirect((string) config('app.auth.home_path', '/dashboard'));
        }
        return $next($request);
    }
}
