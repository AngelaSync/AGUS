<?php

declare(strict_types=1);

namespace App\Services;

use Agus\Http\HttpClient;
use Agus\Support\Cache;

/**
 * Example external API integration (Open-Meteo, free, no API key).
 *
 * Endpoint: GET {base_url}/forecast?latitude=..&longitude=..&current=temperature_2m,weather_code
 * Response: JSON, e.g. {"current": {"temperature_2m": 31.2, "weather_code": 2, "time": "2026-11-14T13:00"}}
 *
 * Results are cached for 10 minutes so page views don't hit the API every time
 * (stays far below Open-Meteo's free limit), and the app keeps working
 * (returns null) if the API is down.
 */
class WeatherService
{
    private const CODES = [
        0 => 'Clear sky', 1 => 'Mostly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
        45 => 'Fog', 48 => 'Fog', 51 => 'Light drizzle', 53 => 'Drizzle', 55 => 'Heavy drizzle',
        61 => 'Light rain', 63 => 'Rain', 65 => 'Heavy rain', 80 => 'Rain showers',
        81 => 'Rain showers', 82 => 'Heavy showers', 95 => 'Thunderstorm', 96 => 'Thunderstorm', 99 => 'Thunderstorm',
    ];

    /** @return array{temperature: float, description: string, time: string}|null */
    public function current(): ?array
    {
        $config = (array) config('services.weather');
        $key = 'weather:' . $config['latitude'] . ',' . $config['longitude'];

        // After a failure, wait 2 minutes before trying again so a slow or
        // offline API doesn't slow down every page.
        if (Cache::has($key . ':down')) {
            return null;
        }

        return Cache::remember($key, (int) ($config['cache_seconds'] ?? 600), function () use ($config, $key): ?array {
            $response = HttpClient::make((string) $config['base_url'])
                ->timeout(6)
                ->retry(1)
                ->get('/forecast', [
                    'latitude' => $config['latitude'],
                    'longitude' => $config['longitude'],
                    'current' => 'temperature_2m,weather_code',
                    'timezone' => config('app.timezone', 'Asia/Manila'),
                ]);

            $current = $response->json()['current'] ?? null;
            if ($response->failed() || !is_array($current) || !isset($current['temperature_2m'])) {
                Cache::put($key . ':down', true, 120);
                return null;
            }

            return [
                'temperature' => (float) $current['temperature_2m'],
                'description' => self::CODES[(int) ($current['weather_code'] ?? -1)] ?? 'Unknown',
                'time' => (string) ($current['time'] ?? ''),
            ];
        });
    }
}
