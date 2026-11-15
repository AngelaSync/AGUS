<?php
/**
 * API routes: return JSON. Everything here is under /api and rate limited.
 * CSRF is skipped for /api/* (see config/app.php 'csrf_except').
 */

use App\Controllers\Api\StatusController;
use Agus\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/api', 'middleware' => ['throttle:60,1']], function (Router $router) {
    $router->get('/health', [StatusController::class, 'health'])->name('api.health');
    $router->get('/weather', [StatusController::class, 'weather'])->name('api.weather');
});
