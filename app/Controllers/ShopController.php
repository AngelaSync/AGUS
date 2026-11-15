<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Services\GeocodingService;
use Agus\Controller;
use Agus\Database\Database;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Auth;

class ShopController extends Controller
{
    /** Form for a customer who wants to sell their surplus food. */
    public function apply(): Response
    {
        return $this->view('shop/apply', ['title' => 'Sell on Replate']);
    }

    public function storeApplication(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|max:120',
            'address' => 'required|max:255',
        ]);

        // OpenStreetMap turns the address into a map pin. If the API is down or can't
        // find it, the shop is still saved and the admin can place the pin by hand.
        $pin = (new GeocodingService())->locate($data['address']);

        Database::transaction(function () use ($data, $pin): void {
            Shop::create([
                'user_id' => Auth::id(),
                'name' => trim($data['name']),
                'address' => trim($data['address']),
                'latitude' => $pin['lat'] ?? null,
                'longitude' => $pin['lon'] ?? null,
                'is_verified' => 0,
            ]);
            $user = User::find((int) Auth::id());
            $user->role = 'shop';
            $user->save();
        });

        $note = $pin ? 'We found it on the map.' : 'We couldn\'t find it on the map yet; the admin will place the pin.';
        return $this->redirect(route('shop.home'))
            ->with('success', 'Shop created! ' . $note . ' Your listings go live once an admin verifies your shop.');
    }

    /** The shop owner's home: shop status, listings and incoming reservations. */
    public function dashboard(): Response
    {
        $shop = Shop::forUser((int) Auth::id()) ?? $this->abort(404, 'No shop found for this account.');

        $reservations = Database::select(
            'SELECT r.*, l.name AS listing_name, u.name AS customer_name
             FROM reservations r
             JOIN listings l ON l.id = r.listing_id
             JOIN users u ON u.id = r.user_id
             WHERE l.shop_id = ?
             ORDER BY CASE WHEN r.status = ? THEN 0 ELSE 1 END, r.created_at DESC, r.id DESC',
            [$shop->id, 'reserved']
        );

        return $this->view('shop/dashboard', [
            'title' => $shop->name,
            'shop' => $shop,
            'listings' => $shop->listings(),
            'reservations' => $reservations,
        ]);
    }

    /** The customer showed their code and paid at the counter. */
    public function pickup(int $id): Response
    {
        $shop = Shop::forUser((int) Auth::id()) ?? $this->abort(404);
        $reservation = Reservation::find($id);
        $listing = $reservation?->listing();
        if (!$reservation || !$listing || (int) $listing->shop_id !== (int) $shop->id) {
            $this->abort(404);
        }
        if (!$reservation->isActive()) {
            return $this->back()->with('error', 'That reservation is already closed.');
        }

        $reservation->status = 'picked_up';
        $reservation->save();
        return $this->back()->with('success', $reservation->pickup_code . ' marked as picked up. Thank you for rescuing food!');
    }
}
