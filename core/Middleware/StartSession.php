<?php

declare(strict_types=1);

namespace Agus\Middleware;

use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Session;
use Agus\View\View;

/** Starts the PHP session and shares the current user and flash messages with views. */
class StartSession implements Middleware
{
    public function handle(Request $request, callable $next, string ...$args): Response
    {
        Session::start();
        Session::ageFlashData();
        View::share('currentUser', auth());

        return $next($request);
    }
}
