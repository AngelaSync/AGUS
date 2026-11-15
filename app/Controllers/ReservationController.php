<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Listing;
use App\Models\Reservation;
use Agus\Controller;
use Agus\Database\Database;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Auth;

class ReservationController extends Controller
{
    /** The customer's reservations, newest first, with the food and shop details. */
    public function index(): Response
    {
        $reservations = Database::select(
            'SELECT r.*, l.name AS listing_name, l.category, l.pickup_window, l.best_before,
                    s.name AS shop_name, s.address AS shop_address
             FROM reservations r
             JOIN listings l ON l.id = r.listing_id
             JOIN shops s ON s.id = l.shop_id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC, r.id DESC',
            [Auth::id()]
        );

        return $this->view('reservations/index', ['title' => 'My reservations', 'reservations' => $reservations]);
    }

    public function store(Request $request, int $id): Response
    {
        $data = $this->validate($request, ['quantity' => 'required|integer|min:1|max:50']);

        try {
            $reservation = Reservation::reserve((int) Auth::id(), $id, (int) $data['quantity']);
        } catch (\DomainException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->redirect(route('reservations.index'))
            ->with('success', 'Reserved! Show code ' . $reservation->pickup_code . ' at the counter and pay there.');
    }

    public function cancel(int $id): Response
    {
        $reservation = Reservation::find($id);
        if (!$reservation || (int) $reservation->user_id !== Auth::id()) {
            $this->abort(404);
        }
        if (!$reservation->isActive()) {
            return $this->back()->with('error', 'Only active reservations can be cancelled.');
        }

        $reservation->cancel();
        return $this->back()->with('success', 'Reservation ' . $reservation->pickup_code . ' cancelled. The food is back on the shelf.');
    }
}
