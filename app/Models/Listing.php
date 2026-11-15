<?php

declare(strict_types=1);

namespace App\Models;

use Agus\Database\Model;
use Agus\Database\QueryBuilder;

class Listing extends Model
{
    protected static string $table = 'listings';

    protected static array $fillable = [
        'shop_id', 'name', 'description', 'category', 'original_price', 'price',
        'quantity', 'allergens', 'photo', 'best_before', 'pickup_window',
    ];

    /** Food types, with the drawings from the Replate prototype. */
    public const CATEGORIES = [
        'bread' => ['label' => 'Bread', 'tone' => 'butter',
            'art' => '<svg viewBox="0 0 64 64" aria-hidden="true"><path d="M10 34c0-11 10-18 22-18s22 7 22 18v12a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4z" fill="#E9A04B"/><path d="M10 34c0-11 10-18 22-18s22 7 22 18" fill="none" stroke="#B4692A" stroke-width="3"/><path d="M22 26l4 6M32 24v7M42 26l-4 6" stroke="#B4692A" stroke-width="3" stroke-linecap="round"/></svg>'],
        'pastries' => ['label' => 'Pastries', 'tone' => 'peach',
            'art' => '<svg viewBox="0 0 64 64" aria-hidden="true"><ellipse cx="32" cy="40" rx="24" ry="12" fill="#E9A04B"/><ellipse cx="32" cy="34" rx="22" ry="13" fill="#F3C676"/><path d="M32 34m-12 0a12 7 0 1 0 24 0a9 5 0 1 0-18 0a5 3 0 1 0 10 0" fill="none" stroke="#B4692A" stroke-width="2.5"/><circle cx="24" cy="30" r="1.6" fill="#FFF6E4"/><circle cx="38" cy="29" r="1.6" fill="#FFF6E4"/><circle cx="33" cy="38" r="1.6" fill="#FFF6E4"/></svg>'],
        'meals' => ['label' => 'Meals', 'tone' => 'lav',
            'art' => '<svg viewBox="0 0 64 64" aria-hidden="true"><path d="M8 32h48a24 18 0 0 1-48 0z" fill="#9475C8"/><path d="M12 32c2-8 10-12 20-12s18 4 20 12z" fill="#FFFCF6"/><circle cx="26" cy="27" r="4" fill="#7A3B1E"/><circle cx="35" cy="25" r="4.5" fill="#8C4524"/><circle cx="42" cy="29" r="3.5" fill="#7A3B1E"/><path d="M20 54h24" stroke="#542E20" stroke-width="3" stroke-linecap="round"/></svg>'],
    ];

    /**
     * Listings customers can see: from a verified shop, still in stock,
     * and not past their best-before date. Shop details come along via a join.
     */
    public static function available(): QueryBuilder
    {
        return static::query()
            ->select(
                'listings.*',
                'shops.name AS shop_name',
                'shops.address AS shop_address',
                'shops.latitude AS shop_latitude',
                'shops.longitude AS shop_longitude'
            )
            ->join('shops', 'shops.id', '=', 'listings.shop_id')
            ->where('shops.is_verified', 1)
            ->where('listings.quantity', '>', 0)
            ->where('listings.best_before', '>=', date('Y-m-d'));
    }

    public static function findAvailable(int $id): ?self
    {
        return static::available()->where('listings.id', $id)->first();
    }

    public function shop(): ?Shop
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? ucfirst((string) $this->category);
    }

    /** The food drawing for this listing's type (inline SVG). */
    public function art(): string
    {
        return self::CATEGORIES[$this->category]['art'] ?? self::CATEGORIES['bread']['art'];
    }

    public function tone(): string
    {
        return self::CATEGORIES[$this->category]['tone'] ?? 'butter';
    }

    /** Allergens as a list: "Wheat, egg" -> ['Wheat', 'Egg'] */
    public function allergenList(): array
    {
        return array_values(array_filter(array_map(fn ($a) => ucfirst(trim($a)), explode(',', (string) $this->allergens))));
    }

    /** Percent off the original price, e.g. 50. */
    public function discount(): int
    {
        $original = (float) $this->original_price;
        return $original > 0 ? (int) round((1 - (float) $this->price / $original) * 100) : 0;
    }

    /** Kilometres from a point, using the shop's map pin (null if the shop has no pin). */
    public function distanceFrom(float $lat, float $lon): ?float
    {
        $shopLat = $this->shop_latitude ?? null;
        $shopLon = $this->shop_longitude ?? null;
        if ($shopLat === null || $shopLon === null) {
            return null;
        }
        return self::haversine($lat, $lon, (float) $shopLat, (float) $shopLon);
    }

    /** Great-circle distance between two coordinates, in kilometres. */
    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
