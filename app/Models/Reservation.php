<?php

declare(strict_types=1);

namespace App\Models;

use Agus\Database\Database;
use Agus\Database\Model;
use Agus\Exceptions\HttpException;

class Reservation extends Model
{
    protected static string $table = 'reservations';

    protected static array $fillable = ['user_id', 'listing_id', 'quantity', 'total', 'pickup_code', 'status'];

    /**
     * Reserves food for a customer. Runs in a transaction, and the stock update only
     * succeeds while enough is left, so two customers can never take the last item.
     */
    public static function reserve(int $userId, int $listingId, int $quantity): self
    {
        return Database::transaction(function () use ($userId, $listingId, $quantity): self {
            $listing = Listing::findAvailable($listingId);
            if (!$listing) {
                throw new HttpException(404, 'That food is no longer available.');
            }
            if ($quantity < 1 || $quantity > (int) $listing->quantity) {
                throw new \DomainException('Only ' . (int) $listing->quantity . ' left. Please choose a smaller quantity.');
            }

            $taken = Listing::query()
                ->where('id', $listingId)
                ->where('quantity', '>=', $quantity)
                ->increment('quantity', -$quantity);
            if ($taken === 0) {
                throw new \DomainException('Someone just reserved the last ones. Please try a smaller quantity.');
            }

            return static::create([
                'user_id' => $userId,
                'listing_id' => $listingId,
                'quantity' => $quantity,
                'total' => round((float) $listing->price * $quantity, 2),
                'pickup_code' => self::newPickupCode(),
                'status' => 'reserved',
            ]);
        });
    }

    /** Cancels a reservation and puts the food back on the shelf. */
    public function cancel(): void
    {
        Database::transaction(function (): void {
            $this->status = 'cancelled';
            $this->save();
            Listing::query()->where('id', $this->listing_id)->increment('quantity', (int) $this->quantity);
        });
    }

    /** Codes like RP-7K4Q. Letters that look alike (0/O, 1/I) are left out. */
    public static function newPickupCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'RP-';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('pickup_code', $code)->exists());
        return $code;
    }

    public function listing(): ?Listing
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'reserved';
    }
}
