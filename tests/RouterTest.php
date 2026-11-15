<?php

use Agus\Exceptions\HttpException;
use Agus\Http\Request;
use Agus\Routing\Router;

return [
    'matches a static route' => function () {
        $r = new Router();
        $r->get('/about', fn () => 'about');
        [$route, $params] = $r->resolve(new Request('GET', '/about'));
        T::assertSame('/about', $route->getPattern());
        T::assertSame([], $params);
    },

    'extracts route parameters with constraints' => function () {
        $r = new Router();
        $r->get('/listings/{id:\d+}', fn () => '');
        [, $params] = $r->resolve(new Request('GET', '/listings/42'));
        T::assertSame(['id' => '42'], $params);
        T::assertThrows(HttpException::class, fn () => $r->resolve(new Request('GET', '/listings/abc')));
    },

    'supports optional parameters' => function () {
        $r = new Router();
        $r->get('/blog/{slug?}', fn () => '');
        [, $none] = $r->resolve(new Request('GET', '/blog'));
        [, $some] = $r->resolve(new Request('GET', '/blog/hello'));
        T::assertSame([], $none);
        T::assertSame(['slug' => 'hello'], $some);
    },

    'returns 405 when the URL exists for another method' => function () {
        $r = new Router();
        $r->post('/login', fn () => '');
        $e = T::assertThrows(HttpException::class, fn () => $r->resolve(new Request('GET', '/login')));
        T::assertSame(405, $e->status());
    },

    'returns 404 for unknown URLs' => function () {
        $e = T::assertThrows(HttpException::class, fn () => (new Router())->resolve(new Request('GET', '/nope')));
        T::assertSame(404, $e->status());
    },

    'groups add prefixes and middleware' => function () {
        $r = new Router();
        $r->group(['prefix' => '/admin', 'middleware' => ['auth']], function (Router $r) {
            $r->get('/users', fn () => '')->middleware('role:admin');
        });
        [$route] = $r->resolve(new Request('GET', '/admin/users'));
        T::assertSame('/admin/users', $route->getPattern());
        T::assertSame(['auth', 'role:admin'], $route->getMiddleware());
    },

    'builds URLs from named routes' => function () {
        $r = new Router();
        $r->get('/shops/{shop}/listings/{id:\d+}', fn () => '')->name('test.listing');
        T::assertSame('/shops/7/listings/3?ref=map', Router::urlFor('test.listing', ['shop' => 7, 'id' => 3, 'ref' => 'map']));
    },

    'resource() registers seven CRUD routes' => function () {
        $r = new Router();
        $r->resource('/posts', 'PostController');
        T::assertSame(7, count($r->routes()));
        [$route, $params] = $r->resolve(new Request('DELETE', '/posts/9'));
        T::assertSame('PostController@destroy', $route->handlerName());
        T::assertSame(['id' => '9'], $params);
    },
];
