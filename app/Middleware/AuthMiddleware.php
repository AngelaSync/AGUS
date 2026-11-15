<?php

declare(strict_types=1);

namespace App\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Middleware\Middleware;
use Agus\Support\Auth;
use Agus\Support\Session;

/**
 * Lets the request through only when someone is signed in.
 * Pages redirect to /login; API calls get a 401 JSON reply.
 */
class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        $user = Auth::user();

        if ($user === null) {
            if ($request->wantsJson()) {
                return Response::json(['message' => 'Please sign in to continue'], 401);
            }
            // Remember where they were going so we can send them back after login.
            if ($request->isMethod('GET')) {
                Session::put('intended', $request->path());
            }
            return Response::redirect((string) config('app.auth.login_path', '/login'))
                ->with('error', 'Please sign in to continue.');
        }

        $request->setAttribute('user', $user);
        return $next($request);
    }
}
