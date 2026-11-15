<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Exceptions\HttpException;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Session;

/**
 * Blocks forged form submissions (CSRF). Every POST/PUT/PATCH/DELETE must
 * carry the session's token, either as the _token form field (csrf_field())
 * or the X-CSRF-TOKEN header (for fetch()).
 *
 * Paths listed in config('app.csrf_except') are skipped, e.g. '/api/*'.
 */
class VerifyCsrfToken implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || $this->isExcepted($request)) {
            return $next($request);
        }

        $sent = (string) ($request->input('_token') ?? $request->header('x-csrf-token') ?? '');
        if ($sent === '' || !hash_equals(Session::token(), $sent)) {
            throw new HttpException(419);
        }
        return $next($request);
    }

    private function isExcepted(Request $request): bool
    {
        foreach ((array) config('app.csrf_except', []) as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote('/' . trim($pattern, '/'), '#')) . '$#';
            if (preg_match($regex, $request->path())) {
                return true;
            }
        }
        return false;
    }
}
