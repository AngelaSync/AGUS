<?php

declare(strict_types=1);

namespace App\Models;

use Agus\Database\Model;

class Shop extends Model
{
    protected static string $table = 'shops';

    protected static array $fillable = ['user_id', 'name', 'address', 'latitude', 'longitude', 'is_verified'];

    public static function forUser(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /** @return Listing[] */
    public function listings(): array
    {
        return Listing::query()->where('shop_id', $this->id)->latest()->get();
    }

    public function owner(): ?User
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hasPin(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isVerified(): bool
    {
        return (bool) $this->is_verified;
    }
}
