<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Shop;
use Agus\Controller;
use Agus\Http\Request;
use Agus\Http\Response;

/** Admin actions on shops. The admin home page is DashboardController::admin. */
class AdminController extends Controller
{
    public function verify(int $id): Response
    {
        $shop = Shop::findOrFail($id);
        $shop->is_verified = $shop->isVerified() ? 0 : 1;
        $shop->save();

        return $this->back()->with('success', $shop->name . ($shop->isVerified() ? ' is verified. Its listings are live.' : ' is hidden again.'));
    }

    /** Places a shop's map pin by hand, when geocoding couldn't find the address. */
    public function location(Request $request, int $id): Response
    {
        $shop = Shop::findOrFail($id);
        $data = $this->validate($request, [
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
        ]);
        $shop->latitude = (float) $data['latitude'];
        $shop->longitude = (float) $data['longitude'];
        $shop->save();

        return $this->back()->with('success', 'Pin saved for ' . $shop->name . '.');
    }
}
