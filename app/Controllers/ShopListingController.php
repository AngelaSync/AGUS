<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Listing;
use App\Models\Shop;
use App\Services\PhotoUpload;
use Agus\Controller;
use Agus\Exceptions\ValidationException;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Auth;

/** Shop owners add, edit and remove their own listings. */
class ShopListingController extends Controller
{
    private const RULES = [
        'name' => 'required|max:120',
        'category' => 'required|in:bread,pastries,meals',
        'description' => 'max:500',
        'original_price' => 'required|numeric|min:1|max:100000',
        'price' => 'required|numeric|min:1|max:100000',
        'quantity' => 'required|integer|min:0|max:999',
        'allergens' => 'max:160',
        'best_before' => 'required|date',
        'pickup_window' => 'required|max:60',
    ];

    public function create(): Response
    {
        return $this->view('shop/listing-form', [
            'title' => 'New listing',
            'listing' => null,
            'shop' => $this->shop(),
        ]);
    }

    public function store(Request $request): Response
    {
        $shop = $this->shop();
        $data = $this->validated($request);
        $data['photo'] = PhotoUpload::store($request->file('photo'));
        $data['shop_id'] = $shop->id;

        $listing = Listing::create($data);
        return $this->redirect(route('shop.home'))->with('success', '"' . $listing->name . '" is posted.');
    }

    public function edit(int $id): Response
    {
        return $this->view('shop/listing-form', [
            'title' => 'Edit listing',
            'listing' => $this->ownListing($id),
            'shop' => $this->shop(),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $listing = $this->ownListing($id);
        $data = $this->validated($request);
        $photo = PhotoUpload::store($request->file('photo'));
        if ($photo !== null) {
            PhotoUpload::delete($listing->photo);
            $data['photo'] = $photo;
        }

        $listing->update($data);
        return $this->redirect(route('shop.home'))->with('success', '"' . $listing->name . '" is updated.');
    }

    public function destroy(int $id): Response
    {
        $listing = $this->ownListing($id);
        PhotoUpload::delete($listing->photo);
        $listing->delete();
        return $this->redirect(route('shop.home'))->with('success', '"' . $listing->name . '" was removed.');
    }

    private function validated(Request $request): array
    {
        $data = $this->validate($request, self::RULES);
        if ((float) $data['price'] > (float) $data['original_price']) {
            throw new ValidationException(['price' => ['The Replate price should be lower than the original price.']], $request->all());
        }
        foreach (['description', 'allergens'] as $optional) {
            $data[$optional] = trim((string) ($data[$optional] ?? '')) ?: null;
        }
        $data['best_before'] = date('Y-m-d', strtotime($data['best_before']));
        return $data;
    }

    private function shop(): Shop
    {
        return Shop::forUser((int) Auth::id()) ?? $this->abort(404, 'No shop found for this account.');
    }

    /** A listing that belongs to the signed-in shop owner, or a 404. */
    private function ownListing(int $id): Listing
    {
        $listing = Listing::find($id);
        if (!$listing || (int) $listing->shop_id !== (int) $this->shop()->id) {
            $this->abort(404);
        }
        return $listing;
    }
}
