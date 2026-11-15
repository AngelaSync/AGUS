<?php
/**
 * Replate features: browsing, reservations with pickup codes, roles and shop sign-up.
 */

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use Agus\App;
use Agus\Database\Migrator;
use Agus\Http\Request;
use Agus\Support\Auth;
use Agus\Support\Cache;
use Agus\Support\Session;

$app = App::instance();
if (!$app->router()->routes()) {
    $app->loadRoutes();
}

$send = fn (string $method, string $path, array $body = [], array $query = []) =>
    App::instance()->handle(new Request($method, $path, $query, $body, [], [], [], ['REMOTE_ADDR' => '10.1.0.' . random_int(1, 250)]));

/** A customer, a verified shop with two listings, and an unverified shop with one. */
$setup = function (): array {
    (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
    $user = fn (string $email, string $role) => User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Auth::hash('password123'), 'role' => $role]);
    $customer = $user('juan@x.com', 'customer');
    $owner = $user('rosa@x.com', 'shop');
    $other = $user('lorna@x.com', 'shop');
    $shop = Shop::create(['user_id' => $owner->id, 'name' => 'Panaderia', 'address' => 'Angeles', 'latitude' => 15.1449, 'longitude' => 120.5887, 'is_verified' => 1]);
    $hidden = Shop::create(['user_id' => $other->id, 'name' => 'Hidden Bakeshop', 'address' => 'Angeles', 'is_verified' => 0]);
    $listing = fn (int $shopId, string $name, string $cat, float $price, int $qty, string $best) => Listing::create([
        'shop_id' => $shopId, 'name' => $name, 'category' => $cat, 'original_price' => $price * 2, 'price' => $price,
        'quantity' => $qty, 'best_before' => $best, 'pickup_window' => '6–8 PM',
    ]);
    return [
        'customer' => $customer, 'owner' => $owner, 'shop' => $shop,
        'pandesal' => $listing($shop->id, 'Pandesal', 'bread', 25, 5, date('Y-m-d', strtotime('+1 day'))),
        'adobo' => $listing($shop->id, 'Adobo', 'meals', 55, 2, date('Y-m-d')),
        'old' => $listing($shop->id, 'Old bread', 'bread', 10, 3, date('Y-m-d', strtotime('-1 day'))),
        'hidden' => $listing($hidden->id, 'Cheese roll', 'bread', 35, 6, date('Y-m-d')),
    ];
};

return [
    'only verified, in-stock, fresh food is listed' => function () use ($setup) {
        $d = $setup();
        Listing::query()->where('id', $d['adobo']->id)->update(['quantity' => 0]);
        $names = array_map(fn ($l) => $l->name, Listing::available()->get());
        T::assertSame(['Pandesal'], $names, 'sold out, expired and unverified-shop food stay hidden');
    },

    'browse page filters by food type and price' => function () use ($setup, $send) {
        $setup();
        $meals = $send('GET', '/', [], ['category' => 'meals'])->body();
        T::assertContains('class="name">Adobo<', $meals);
        T::assertTrue(!str_contains($meals, 'class="name">Pandesal<'), 'bread is filtered out');
        $cheap = $send('GET', '/', [], ['max_price' => '50'])->body();
        T::assertContains('class="name">Pandesal<', $cheap);
        T::assertTrue(!str_contains($cheap, 'class="name">Adobo<'), 'food above the max price is filtered out');
    },

    'reserving takes stock and gives a pickup code' => function () use ($setup) {
        $d = $setup();
        $r = Reservation::reserve((int) $d['customer']->id, (int) $d['pandesal']->id, 2);
        T::assertTrue((bool) preg_match('/^RP-[A-HJ-NP-Z2-9]{4}$/', (string) $r->pickup_code), 'code looks like RP-7K4Q');
        T::assertSame(50.0, (float) $r->total);
        T::assertSame(3, (int) $d['pandesal']->refresh()->quantity);
    },

    'nobody can reserve more than what is left' => function () use ($setup) {
        $d = $setup();
        T::assertThrows(\DomainException::class, fn () => Reservation::reserve((int) $d['customer']->id, (int) $d['adobo']->id, 3));
        T::assertSame(2, (int) $d['adobo']->refresh()->quantity, 'stock is unchanged');
        T::assertSame(0, Reservation::query()->count());
    },

    'cancelling puts the food back' => function () use ($setup) {
        $d = $setup();
        $r = Reservation::reserve((int) $d['customer']->id, (int) $d['adobo']->id, 2);
        $r->cancel();
        T::assertSame('cancelled', $r->refresh()->status);
        T::assertSame(2, (int) $d['adobo']->refresh()->quantity);
    },

    'customer reserves through the website' => function () use ($setup, $send) {
        $d = $setup();
        Auth::attempt('juan@x.com', 'password123');
        $res = $send('POST', '/listings/' . $d['pandesal']->id . '/reserve', ['_token' => Session::token(), 'quantity' => 1]);
        T::assertSame(302, $res->status());
        $code = Reservation::query()->first()->pickup_code;
        T::assertContains($code, $send('GET', '/reservations')->body());
    },

    'each role only reaches its own pages' => function () use ($setup, $send) {
        $setup();
        Auth::attempt('juan@x.com', 'password123');
        T::assertSame(403, $send('GET', '/shop')->status(), 'customers cannot open the shop dashboard');
        Auth::logout();
        Auth::attempt('rosa@x.com', 'password123');
        T::assertSame(200, $send('GET', '/shop')->status());
        T::assertSame(403, $send('GET', '/reservations')->status(), 'shops cannot reserve');
    },

    'shops only close their own pickups' => function () use ($setup, $send) {
        $d = $setup();
        $r = Reservation::reserve((int) $d['customer']->id, (int) $d['pandesal']->id, 1);
        Auth::attempt('lorna@x.com', 'password123');
        T::assertSame(404, $send('POST', '/shop/reservations/' . $r->id . '/pickup', ['_token' => Session::token()])->status());
        Auth::logout();
        Auth::attempt('rosa@x.com', 'password123');
        $send('POST', '/shop/reservations/' . $r->id . '/pickup', ['_token' => Session::token()]);
        T::assertSame('picked_up', $r->refresh()->status);
    },

    'selling food creates an unverified shop with a map pin' => function () use ($setup, $send) {
        $setup();
        User::create(['name' => 'Pedro', 'email' => 'pedro@x.com', 'password' => Auth::hash('password123'), 'role' => 'customer']);
        Auth::attempt('pedro@x.com', 'password123');
        $address = 'Sto. Domingo, Angeles City';
        Cache::put('geo:' . strtolower($address), ['lat' => 15.15, 'lon' => 120.59], 60); // no real API call in tests
        $send('POST', '/sell', ['_token' => Session::token(), 'name' => 'Pedro Puto', 'address' => $address]);
        Cache::forget('geo:' . strtolower($address));

        $shop = Shop::forUser((int) User::findByEmail('pedro@x.com')->id);
        T::assertTrue($shop !== null && !$shop->isVerified(), 'shop waits for the admin');
        T::assertSame(15.15, (float) $shop->latitude);
        T::assertSame('shop', User::findByEmail('pedro@x.com')->role);
    },

    'distance between two shops is about right' => function () {
        $km = Listing::haversine(15.1449, 120.5887, 15.1388, 120.5869);
        T::assertTrue($km > 0.6 && $km < 0.8, "Sto. Rosario to Nepo Mart is about 0.7 km, got $km");
    },
];
