<?php

declare(strict_types=1);

namespace App\Services;

use Agus\Http\HttpClient;
use Agus\Support\Cache;

/**
 * Turns a shop's address into map coordinates with OpenStreetMap Nominatim.
 *
 * Endpoint: GET {base_url}/search?q=<address>&format=json&limit=1
 * Response: JSON list, e.g. [{"lat": "15.1449", "lon": "120.5887", "display_name": "..."}]
 *
 * Usage policy: no API key, but every app must send a User-Agent that names it,
 * and make at most 1 request per second. So each address is looked up once and
 * cached forever; page views never call the API.
 */
class GeocodingService
{
    /** @return array{lat: float, lon: float}|null */
    public function locate(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $config = (array) config('services.geocoding');
        $key = 'geo:' . strtolower($address);

        return Cache::remember($key, 0, function () use ($config, $address): ?array {
            $response = HttpClient::make((string) $config['base_url'])
                ->withHeaders(['User-Agent' => (string) $config['user_agent']])
                ->timeout(8)
                ->retry(2, 1000)
                ->get('/search', [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'ph',
                ]);

            $first = $response->json()[0] ?? null;
            if ($response->failed() || !isset($first['lat'], $first['lon'])) {
                return null; // the shop still saves; the admin can set the pin by hand
            }

            return ['lat' => (float) $first['lat'], 'lon' => (float) $first['lon']];
        });
    }
}
