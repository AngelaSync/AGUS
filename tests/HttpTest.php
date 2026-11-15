<?php
/**
 * End-to-end tests: send fake requests through the whole app
 * (middleware, router, controller, views) and check the responses.
 */

use Agus\App;
use Agus\Database\Migrator;
use Agus\Http\Request;
use Agus\Support\Auth;
use Agus\Support\Cache;
use Agus\Support\Config;
use Agus\Support\Session;

$app = App::instance();
if (!$app->router()->routes()) {
    $app->loadRoutes();
}
Config::set('services.weather.base_url', 'http://127.0.0.1:9'); // never call the real API in tests

$send = fn (string $method, string $path, array $body = [], array $headers = []) =>
    App::instance()->handle(new Request($method, $path, [], $body, $headers, [], [], ['REMOTE_ADDR' => '10.0.0.' . random_int(1, 250)]));

return [
    'home page renders' => function () use ($send) {
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        $res = $send('GET', '/');
        T::assertSame(200, $res->status());
        T::assertContains('nearby, for less', $res->body());
        T::assertSame('nosniff', $res->getHeader('X-Content-Type-Options'));
    },

    'unknown page gives a 404 page' => function () use ($send) {
        $res = $send('GET', '/does-not-exist');
        T::assertSame(404, $res->status());
        T::assertContains('Page not found', $res->body());
    },

    'api errors are JSON' => function () use ($send) {
        $res = $send('GET', '/api/nope');
        T::assertSame(404, $res->status());
        T::assertSame('Page not found', json_decode($res->body(), true)['message']);
    },

    'api health reports the database' => function () use ($send) {
        $res = $send('GET', '/api/health');
        T::assertSame(200, $res->status());
        T::assertSame('ok', json_decode($res->body(), true)['database']);
    },

    'dashboard redirects guests to login' => function () use ($send) {
        $res = $send('GET', '/dashboard');
        T::assertSame(302, $res->status());
        T::assertTrue(str_ends_with((string) $res->getHeader('Location'), '/login'));
    },

    'POST without a CSRF token is rejected' => function () use ($send) {
        $res = $send('POST', '/login', ['email' => 'a@b.com', 'password' => 'x']);
        T::assertSame(419, $res->status());
    },

    'register validates, creates the user and signs in' => function () use ($send) {
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        $token = Session::token();

        $bad = $send('POST', '/register', ['_token' => $token, 'name' => 'Ana', 'email' => 'bad', 'password' => 'short', 'password_confirmation' => 'short']);
        T::assertSame(302, $bad->status());
        T::assertTrue(isset(Session::getFlash('_errors')['email']), 'email error flashed');

        $ok = $send('POST', '/register', ['_token' => $token, 'name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        T::assertSame(302, $ok->status());
        T::assertTrue(Auth::check(), 'user is signed in');

        $dash = $send('GET', '/dashboard');
        T::assertSame(302, $dash->status(), 'customers are sent to the browse page');
        T::assertContains('Sign out', $send('GET', '/profile')->body());
    },

    'admin area needs the admin role' => function () use ($send) {
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        Agus\Database\Database::table('users')->insert(['name' => 'C', 'email' => 'c@x.com', 'password' => Auth::hash('password123'), 'role' => 'customer']);
        Auth::attempt('c@x.com', 'password123');
        T::assertSame(403, $send('GET', '/admin')->status());
    },

    'login is rate limited' => function () use ($send) {
        $headers = [];
        $request = fn () => App::instance()->handle(new Request('POST', '/login', [], ['_token' => Session::token(), 'email' => 'x@x.com', 'password' => 'nope'], [], [], [], ['REMOTE_ADDR' => '10.9.9.9']));
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        Cache::forget('throttle:10.9.9.9:POST:/login');
        for ($i = 0; $i < 5; $i++) {
            T::assertSame(302, $request()->status());
        }
        T::assertSame(429, $request()->status(), 'sixth attempt in a minute is blocked');
        Cache::forget('throttle:10.9.9.9:POST:/login');
    },
];
