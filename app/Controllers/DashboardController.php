<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use Agus\Controller;
use Agus\Http\Request;
use Agus\Http\Response;

class DashboardController extends Controller
{
    /** Sends each role to its own home page. */
    public function index(Request $request): Response
    {
        return match ($request->user()['role'] ?? 'customer') {
            'admin' => $this->redirect(route('admin.home')),
            'shop' => $this->redirect(route('shop.home')),
            default => $this->redirect(route('home')),
        };
    }

    /** Only reachable with the 'role:admin' middleware. */
    public function admin(): Response
    {
        return $this->view('admin/index', [
            'title' => 'Admin',
            'pending' => Shop::query()->where('is_verified', 0)->latest()->get(),
            'verified' => Shop::query()->where('is_verified', 1)->orderBy('name')->get(),
            'stats' => [
                'users' => User::query()->count(),
                'shops' => Shop::query()->count(),
                'listings' => Listing::available()->count(),
                'rescued' => (int) Reservation::query()->where('status', 'picked_up')->sum('quantity'),
            ],
            'users' => User::query()->latest()->paginate(8),
        ]);
    }
}
