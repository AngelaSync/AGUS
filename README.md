# AGUS

A small MVC framework written from scratch in plain PHP 8.1+ (no Composer, no outside packages) for the IPT10 final project. AGUS is the framework; Replate is the app built on it.

It covers every framework item in the project guidelines:

| Guideline item | Where it lives |
|---|---|
| MVC pattern, folder structure | `app/Controllers`, `app/Models`, `app/Views` |
| Routing | `core/Routing/Router.php`, `core/Routing/Route.php`, `routes/web.php` |
| Middleware (filtering, auth, logging) | `core/Middleware/*`, `app/Middleware/*` |
| Request, Response, Router, Database classes | `core/Http/Request.php`, `core/Http/Response.php`, `core/Routing/Router.php`, `core/Database/Database.php` |
| Configuration | `.env`, `config/app.php`, `config/database.php`, `config/services.php` |
| Database + migrations | `core/Database/*`, `database/migrations/*` |
| External API support | `core/Http/HttpClient.php`, example in `app/Services/WeatherService.php` |
| Testing | `tests/*` (41 tests, run with `php agus test`) |

## Getting started

### Option A: XAMPP (MySQL)

1. Copy the `agus` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Copy `.env.example` to `.env` (the defaults match XAMPP: user `root`, no password, database `agus`).
4. Open a terminal in the folder and run:
   ```
   php agus db:create
   php agus migrate
   php agus db:seed
   ```
5. Open **http://localhost/agus/**

If `php` isn't recognized on Windows, use `C:\xampp\php\php.exe agus migrate`, or add `C:\xampp\php` to your PATH.

### Option B: built-in server (no Apache needed)

```
php agus serve
```
Then open **http://localhost:8000**. Set `DB_DRIVER=sqlite` in `.env` if you don't want to run MySQL at all.

### Sample accounts (after `db:seed`)

| Email | Password | Role |
|---|---|---|
| admin@example.com | password123 | admin |
| juan@example.com | password123 | customer |
| rosa@example.com | password123 | shop (Panaderia ni Aling Rosa) |
| ben@example.com | password123 | shop (Kusina ni Mang Ben) |
| carla@example.com | password123 | shop (Sweet Crumbs Café) |
| lorna@example.com | password123 | shop, waiting for admin verification |

Run `php agus db:seed` again any time to reset the demo: the food is "tonight" again and back in stock.

## The Replate app

Replate is the app built on AGUS: bakeries and carinderias in Angeles City sell leftover food at a discount.

| Who | Can do | Where |
|---|---|---|
| Anyone | Browse food; search and filter by type, price and distance; see shops on a map | `/`, `/listings/{id}`, `/map` |
| Customer | Reserve food and get a pickup code, cancel, apply to sell | `/reservations`, `/sell` |
| Shop | Post, edit and remove listings with photos; mark pickups as done | `/shop` |
| Admin | Verify shops, place map pins by hand | `/admin` |

Tables: `users`, `shops`, `listings`, `reservations` (see `database/migrations`). Shop addresses become map pins through `app/Services/GeocodingService.php` (OpenStreetMap Nominatim). The map uses Leaflet, bundled in `public/assets/vendor/leaflet`.

## Folder structure

```
agus/
├── app/                    Your application (namespace App\)
│   ├── Controllers/        One class per area; methods are "actions"
│   ├── Middleware/         Auth, Guest, Role checks
│   ├── Models/             One class per table
│   ├── Services/           Code that talks to external APIs
│   └── Views/              PHP templates (layouts/, partials/, errors/)
├── config/                 app.php, database.php, services.php
├── core/                   The framework itself (namespace Agus\)
│   ├── App.php             Request lifecycle (kernel)
│   ├── Controller.php      Base controller: view(), json(), validate()
│   ├── Http/               Request, Response, HttpClient, HttpResponse
│   ├── Routing/            Router, Route
│   ├── Middleware/         Middleware interface, Pipeline, built-in middleware
│   ├── Database/           Database (PDO), QueryBuilder, Model, Migrator, Schema/
│   ├── View/               View renderer with layouts and sections
│   ├── Support/            Env, Config, Session, Auth, Validator, Cache, Logger, helpers
│   ├── Console/            The `php agus` command-line tool
│   └── bootstrap.php       Autoloader + .env + config
├── database/
│   ├── migrations/         Table definitions, run in filename order
│   └── seeders/            Sample data
├── public/                 The only folder the browser can reach
│   ├── index.php           Front controller (every request starts here)
│   └── assets/             CSS, JS, images
├── routes/                 web.php (pages), api.php (JSON)
├── storage/                logs/, cache/, sessions/ (not in Git)
├── tests/                  Test runner and tests
└── agus                 CLI entry: php agus <command>
```

**Naming conventions:** classes in `PascalCase` with a suffix for their role (`ListingController`, `AuthMiddleware`); models are singular (`Listing`) and tables plural snake_case (`listings`); views are lowercase paths (`listings/show.php` → `$this->view('listings/show')`); migrations are `YYYY_MM_DD_HHMMSS_description.php`.

## How a request flows

```
Browser → public/index.php → Request::capture()
        → global middleware: LogRequests → SecurityHeaders → StartSession → VerifyCsrfToken
        → Router::resolve() finds the route (404 / 405 if none)
        → route middleware: e.g. auth → role:admin → throttle:5,1
        → Controller action returns a Response (or string → HTML, array → JSON)
        → Response::send()
```

## Cheat sheet

### Routes (`routes/web.php`)

```php
$router->get('/listings', [ListingController::class, 'index'])->name('listings.index');
$router->get('/listings/{id:\d+}', [ListingController::class, 'show'])->name('listings.show');
$router->post('/listings', [ListingController::class, 'store'])->middleware('auth');
$router->resource('/admin/posts', PostController::class);   // 7 CRUD routes at once

$router->group(['prefix' => '/shop', 'middleware' => ['auth', 'role:shop']], function (Router $router) {
    $router->get('/dashboard', [ShopController::class, 'dashboard']);
});
```
Route params are passed to the action by name: `public function show(int $id)`.

### Controllers

```php
class ListingController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'  => 'required|max:80',
            'price' => 'required|numeric|min:1',
        ]);
        $listing = Listing::create($data + ['shop_id' => $request->user()['id']]);
        return $this->redirect(route('listings.show', ['id' => $listing->id]))->with('success', 'Posted!');
    }
}
```
Validation rules: `required, email, numeric, integer, min, max, in, confirmed, same, regex, date, url, unique:table,column, exists:table,column`.

### Database

```php
Database::table('listings')->where('price', '<', 50)->orderBy('created_at', 'desc')->paginate(10);
Database::table('listings')->where('id', $id)->increment('quantity', -1);
Database::transaction(function () { /* several writes that must all succeed */ });

Listing::find(5);  Listing::where('shop_id', 2)->get();  $listing->update(['price' => 30]);
```

### Views

```php
<?php $this->layout('layouts/main', ['title' => 'Listings']) ?>
<h1><?= e($listing->name) ?></h1>          <!-- always escape with e() -->
<form method="post" action="<?= e(url('/listings')) ?>">
    <?= csrf_field() ?>
    <input name="name" value="<?= e(old('name')) ?>">
    <?php if ($err = errors('name')): ?><p class="field-error"><?= e($err) ?></p><?php endif ?>
</form>
```

### Calling an external API

```php
$response = HttpClient::make(config('services.example.base_url'))
    ->withToken(config('services.example.key'))   // key comes from .env
    ->timeout(8)
    ->retry(2)                                      // retries on 429 / 5xx with back-off
    ->get('/v1/search', ['q' => 'bread']);

if ($response->failed()) { /* show a friendly message; failure is logged */ }
$data = $response->json();
```
Wrap it in `Cache::remember('key', 600, fn () => ...)` so you don't hit the API's rate limit.

## Commands

```
php agus serve [--port=8080]       php agus route:list
php agus db:create                 php agus make:controller ListingController [--resource]
php agus migrate                   php agus make:model Listing
php agus migrate:status            php agus make:middleware ShopOwnerMiddleware
php agus migrate:rollback          php agus make:migration create_listings_table
php agus migrate:fresh             php agus test
php agus db:seed
```

## Security built in

- Prepared statements for every value; table and column names are checked against a safe pattern.
- CSRF tokens on every POST/PUT/PATCH/DELETE form.
- Passwords hashed with `password_hash()` (bcrypt); session id regenerated on login and logout.
- `$fillable` on models stops users from setting columns like `role` through a form.
- `e()` escapes output; session cookies are HttpOnly and SameSite=Lax.
- Rate limiting with `throttle:max,minutes`; security headers on every response.
- `.env`, `app/` and `storage/` are outside `public/`, so the browser can't download them.
- `APP_DEBUG=false` hides error details from visitors and logs them to `storage/logs/` instead.
