<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Listing;
use App\Models\Shop;
use Agus\Controller;
use Agus\Http\Response;

class ListingController extends Controller
{
    public function show(int $id): Response
    {
        $listing = Listing::findAvailable($id) ?? $this->abort(404, 'That food is sold out or no longer available.');

        return $this->view('listings/show', [
            'title' => $listing->name,
            'listing' => $listing,
        ]);
    }

    /** Map of every verified shop that has a pin. */
    public function map(): Response
    {
        $shops = Shop::query()->where('is_verified', 1)->whereNotNull('latitude')->orderBy('name')->get();

        $pins = [];
        foreach ($shops as $shop) {
            $count = Listing::available()->where('listings.shop_id', $shop->id)->count();
            $pins[] = [
                'name' => $shop->name,
                'address' => $shop->address,
                'lat' => (float) $shop->latitude,
                'lon' => (float) $shop->longitude,
                'listings' => $count,
                'url' => url('/?q=' . rawurlencode((string) $shop->name)),
            ];
        }

        return $this->view('listings/map', ['title' => 'Shops near you', 'pins' => $pins]);
    }

    /** Profile tab: account details and role links, or sign-in buttons for guests. */
    public function profile(): Response
    {
        return $this->view('profile/index', ['title' => 'Profile']);
    }
}
