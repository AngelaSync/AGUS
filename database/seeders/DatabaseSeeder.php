<?php
/**
 * Sample data for development and the demo. Run: php agus db:seed
 * Safe to run twice: it skips accounts and shops that already exist,
 * and refreshes the listings' dates so they're always "tonight".
 */

use App\Models\Listing;
use App\Models\Shop;
use App\Models\User;
use Agus\Support\Auth;

return function (): void {
    $accounts = [
        ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin'],
        ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'role' => 'customer'],
        ['name' => 'Rosa Santos', 'email' => 'rosa@example.com', 'role' => 'shop'],
        ['name' => 'Ben Manalo', 'email' => 'ben@example.com', 'role' => 'shop'],
        ['name' => 'Carla Reyes', 'email' => 'carla@example.com', 'role' => 'shop'],
        ['name' => 'Lorna Pineda', 'email' => 'lorna@example.com', 'role' => 'shop'],
    ];
    $users = [];
    foreach ($accounts as $account) {
        $user = User::findByEmail($account['email']);
        if (!$user) {
            $user = User::create($account + ['password' => Auth::hash('password123')]);
            echo "  Created {$account['email']} (password: password123)\n";
        }
        $users[$account['email']] = $user;
    }

    // Coordinates are around Angeles City, Pampanga. Lorna's shop is waiting for the admin.
    $shops = [
        'rosa@example.com' => ['Panaderia ni Aling Rosa', 'Sto. Rosario St, Angeles City, Pampanga', 15.1449, 120.5887, 1],
        'ben@example.com' => ['Kusina ni Mang Ben', 'Nepo Mart, Angeles City, Pampanga', 15.1388, 120.5869, 1],
        'carla@example.com' => ['Sweet Crumbs Café', 'MacArthur Hwy, Balibago, Angeles City, Pampanga', 15.1626, 120.5911, 1],
        'lorna@example.com' => ["Tita Lorna's Bakeshop", 'Pampang Public Market, Angeles City, Pampanga', 15.1437, 120.5949, 0],
    ];
    $shopIds = [];
    foreach ($shops as $email => [$name, $address, $lat, $lon, $verified]) {
        $shop = Shop::forUser((int) $users[$email]->id) ?? Shop::create([
            'user_id' => $users[$email]->id, 'name' => $name, 'address' => $address,
            'latitude' => $lat, 'longitude' => $lon, 'is_verified' => $verified,
        ]);
        $shopIds[$email] = (int) $shop->id;
    }

    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $listings = [
        // shop, name, category, original, price, qty, allergens, best before, pickup, description
        ['rosa@example.com', 'Pandesal (bag of 10)', 'bread', 50, 25, 8, 'Wheat', $tomorrow, '6:00–8:00 PM', 'Baked this morning. Still soft, perfect for tomorrow\'s breakfast.'],
        ['rosa@example.com', 'Ensaymada (box of 4)', 'pastries', 120, 60, 5, 'Wheat, egg, milk', $tomorrow, '6:00–8:00 PM', 'Buttery ensaymada topped with sugar and cheese.'],
        ['rosa@example.com', 'Spanish bread (6 pcs)', 'pastries', 60, 30, 3, 'Wheat, milk', $tomorrow, '6:00–8:00 PM', null],
        ['ben@example.com', 'Chicken adobo with rice', 'meals', 110, 55, 6, 'Soy', $today, '7:00–9:00 PM', 'Today\'s ulam, packed with a cup of rice. Reheat before eating.'],
        ['ben@example.com', 'Pancit canton (good for 3)', 'meals', 180, 90, 2, 'Wheat, soy, shellfish', $today, '7:00–9:00 PM', 'Stir-fried noodles with vegetables and pork.'],
        ['ben@example.com', 'Sinigang na baboy', 'meals', 130, 65, 4, null, $today, '7:00–9:00 PM', 'Sour tamarind soup with pork and vegetables.'],
        ['carla@example.com', 'Ube cake slice', 'pastries', 95, 45, 7, 'Wheat, egg, milk', $tomorrow, '8:00–9:30 PM', 'Moist ube chiffon with ube halaya frosting.'],
        ['carla@example.com', 'Egg pie slices (2 pcs)', 'pastries', 110, 55, 3, 'Wheat, egg, milk', $tomorrow, '8:00–9:30 PM', 'Classic egg pie with a golden top.'],
        ['carla@example.com', 'Banana bread loaf', 'bread', 160, 80, 2, 'Wheat, egg, nuts', $tomorrow, '8:00–9:30 PM', 'Made with ripe saba bananas and walnuts.'],
        ['carla@example.com', 'Cheese bread (6 pcs)', 'bread', 70, 35, 5, 'Wheat, milk', $tomorrow, '8:00–9:30 PM', 'Soft rolls with a sweet cheese topping.'],
        ['lorna@example.com', 'Cheese rolls (pack of 6)', 'bread', 70, 35, 6, 'Wheat, milk', $tomorrow, '5:00–7:00 PM', 'Visible once the admin verifies the shop.'],
    ];
    foreach ($listings as [$email, $name, $category, $original, $price, $qty, $allergens, $best, $pickup, $desc]) {
        $existing = Listing::query()->where('shop_id', $shopIds[$email])->where('name', $name)->first();
        if ($existing) {
            // Re-seeding resets the demo: food is "tonight" again and back in stock.
            $existing->best_before = $best;
            $existing->quantity = $qty;
            $existing->save();
            continue;
        }
        Listing::create([
            'shop_id' => $shopIds[$email], 'name' => $name, 'category' => $category,
            'original_price' => $original, 'price' => $price, 'quantity' => $qty,
            'allergens' => $allergens, 'best_before' => $best, 'pickup_window' => $pickup, 'description' => $desc,
        ]);
    }
    echo '  ' . count($shops) . " shops and " . count($listings) . " listings ready.\n";
};
