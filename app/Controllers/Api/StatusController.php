<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\WeatherService;
use Agus\Controller;
use Agus\Database\Database;
use Agus\Http\Response;

class StatusController extends Controller
{
    /** GET /api/health -> is the app up and can it reach the database? */
    public function health(): Response
    {
        try {
            Database::selectOne('SELECT 1 AS ok');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'unreachable';
        }

        return $this->json([
            'app' => config('app.name'),
            'status' => 'ok',
            'database' => $db,
            'time' => date(DATE_ATOM),
        ], $db === 'ok' ? 200 : 503);
    }

    /** GET /api/weather -> current weather from the external API (cached). */
    public function weather(): Response
    {
        $weather = (new WeatherService())->current();
        if ($weather === null) {
            return $this->json(['message' => 'Weather service is unavailable right now. Try again later.'], 503);
        }
        return $this->json($weather);
    }
}
