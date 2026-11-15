<?php

declare(strict_types=1);

namespace App\Middleware;

use Agus\Exceptions\HttpException;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Middleware\Middleware;
use Agus\Support\Auth;

/**
 * Allows only users with one of the given roles.
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,shop')
 * Put 'auth' before it so there is always a signed-in user.
 */
class RoleMiddleware implements Middleware
{
    public function handle(Request $request, callable $next, string ...$roles): Response
    {
        $role = Auth::user()['role'] ?? null;
        if ($role === null || !in_array($role, $roles, true)) {
            throw new HttpException(403);
        }
        return $next($request);
    }
}
