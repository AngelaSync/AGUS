<?php
/**
 * Settings for external APIs. Keys live in .env, never in code or Git.
 * Read them with config('services.weather.base_url').
 */

return [
    // Open-Meteo: free weather API, no key needed. Used by the example WeatherService.
    'weather' => [
        'base_url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1'),
        'latitude' => env('WEATHER_LAT', '15.1450'),   // Angeles City
        'longitude' => env('WEATHER_LON', '120.5887'),
        'cache_seconds' => 600,
    ],

    // OpenStreetMap Nominatim: turns shop addresses into map pins. No key, but the
    // usage policy requires a User-Agent that names the app, and max 1 request/second.
    'geocoding' => [
        'base_url' => env('GEOCODING_API_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('GEOCODING_USER_AGENT', 'Replate/1.0 (IPT10 student project)'),
    ],

    // Example of a key-based API. Put the real key in .env as EXAMPLE_API_KEY=...
    'example' => [
        'base_url' => env('EXAMPLE_API_URL', ''),
        'key' => env('EXAMPLE_API_KEY', ''),
    ],
];
