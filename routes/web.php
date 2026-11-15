<?php
/**
 * Web routes: pages that return HTML.
 * $router is provided by App::loadRoutes().
 *
 *   $router->get('/path/{id:\d+}', [Controller::class, 'method'])->name('name')->middleware('auth');
 */

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use Agus\Routing\Router;

/** @var Router $router */

$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/about', [HomeController::class, 'about'])->name('about');

// Only for visitors who are NOT signed in
$router->group(['middleware' => ['guest']], function (Router $router) {
    $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
    $router->post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    $router->get('/register', [AuthController::class, 'showRegister'])->name('register');
    $router->post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

// Only for signed-in users
$router->group(['middleware' => ['auth']], function (Router $router) {
    $router->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    $router->post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Only for admins: 'role:admin' passes "admin" to RoleMiddleware
$router->group(['prefix' => '/admin', 'middleware' => ['auth', 'role:admin']], function (Router $router) {
    $router->get('/', [DashboardController::class, 'admin'])->name('admin.home');
});

// ---- Replate ----------------------------------------------------------------

use App\Controllers\AdminController;
use App\Controllers\ListingController;
use App\Controllers\ReservationController;
use App\Controllers\ShopController;
use App\Controllers\ShopListingController;

// Anyone can browse food and see the shops on a map
$router->get('/listings/{id:\d+}', [ListingController::class, 'show'])->name('listings.show');
$router->get('/map', [ListingController::class, 'map'])->name('map');
$router->get('/profile', [ListingController::class, 'profile'])->name('profile');

// Customers reserve food, and can apply to sell their own
$router->group(['middleware' => ['auth', 'role:customer']], function (Router $router) {
    $router->post('/listings/{id:\d+}/reserve', [ReservationController::class, 'store'])->name('reservations.store');
    $router->get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    $router->post('/reservations/{id:\d+}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    $router->get('/sell', [ShopController::class, 'apply'])->name('shop.apply');
    $router->post('/sell', [ShopController::class, 'storeApplication']);
});

// Shop owners manage their listings and pickups
$router->group(['prefix' => '/shop', 'middleware' => ['auth', 'role:shop']], function (Router $router) {
    $router->get('/', [ShopController::class, 'dashboard'])->name('shop.home');
    $router->get('/listings/create', [ShopListingController::class, 'create'])->name('shop.listings.create');
    $router->post('/listings', [ShopListingController::class, 'store'])->name('shop.listings.store');
    $router->get('/listings/{id:\d+}/edit', [ShopListingController::class, 'edit'])->name('shop.listings.edit');
    $router->post('/listings/{id:\d+}', [ShopListingController::class, 'update'])->name('shop.listings.update');
    $router->post('/listings/{id:\d+}/delete', [ShopListingController::class, 'destroy'])->name('shop.listings.destroy');
    $router->post('/reservations/{id:\d+}/pickup', [ShopController::class, 'pickup'])->name('shop.reservations.pickup');
});

// Admins verify shops and fix map pins
$router->group(['prefix' => '/admin', 'middleware' => ['auth', 'role:admin']], function (Router $router) {
    $router->post('/shops/{id:\d+}/verify', [AdminController::class, 'verify'])->name('admin.shops.verify');
    $router->post('/shops/{id:\d+}/location', [AdminController::class, 'location'])->name('admin.shops.location');
});
