<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Listing;
use Agus\Controller;
use Agus\Http\Request;
use Agus\Http\Response;

class HomeController extends Controller
{
    /** Angeles City, used for distances until the customer shares their location. */
    public const DEFAULT_LAT = 15.1450;
    public const DEFAULT_LON = 120.5887;

    /** Browse page: search, filter by food type, price and distance, then sort. */
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => (string) $request->query('category', ''),
            'max_price' => (string) $request->query('max_price', ''),
            'radius' => (string) $request->query('radius', ''),
            'sort' => (string) $request->query('sort', 'nearest'),
            'lat' => (string) $request->query('lat', ''),
            'lon' => (string) $request->query('lon', ''),
        ];

        $query = Listing::available()->search(['listings.name', 'listings.description', 'shops.name'], $filters['q']);
        if (isset(Listing::CATEGORIES[$filters['category']])) {
            $query->where('listings.category', $filters['category']);
        }
        if (is_numeric($filters['max_price'])) {
            $query->where('listings.price', '<=', (float) $filters['max_price']);
        }

        $usingMyLocation = is_numeric($filters['lat']) && is_numeric($filters['lon']);
        $lat = $usingMyLocation ? (float) $filters['lat'] : self::DEFAULT_LAT;
        $lon = $usingMyLocation ? (float) $filters['lon'] : self::DEFAULT_LON;

        $listings = $query->get();
        foreach ($listings as $listing) {
            $listing->distance = $listing->distanceFrom($lat, $lon);
        }
        if (is_numeric($filters['radius'])) {
            $radius = (float) $filters['radius'];
            $listings = array_values(array_filter($listings, fn (Listing $l) => $l->distance !== null && $l->distance <= $radius));
        }

        usort($listings, match ($filters['sort']) {
            'cheapest' => fn (Listing $a, Listing $b) => (float) $a->price <=> (float) $b->price,
            'newest' => fn (Listing $a, Listing $b) => strcmp((string) $b->created_at, (string) $a->created_at),
            default => fn (Listing $a, Listing $b) => ($a->distance ?? INF) <=> ($b->distance ?? INF),
        });

        return $this->view('listings/index', [
            'title' => 'Rescue good food tonight',
            'listings' => $listings,
            'filters' => $filters,
            'usingMyLocation' => $usingMyLocation,
        ]);
    }

    public function about(): Response
    {
        return $this->view('home/about', ['title' => 'About']);
    }
}
