<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shop;
use Agus\Database\Database;

/** Small helpers for the Replate layout: icons from the prototype and the bell's count. */
final class Ui
{
    public const ICONS = [
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
        'browse' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11h16"/><path d="M5 11a7 7 0 0 1 14 0"/><path d="M4 15h16l-2 4H6z"/></svg>',
        'map' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z"/><circle cx="12" cy="10" r="2.3"/></svg>',
        'res' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="17" rx="3"/><path d="M8 2v4M16 2v4M8 12l3 3 5-6"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        'dash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg>',
        'refresh' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v5h-5"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>',
    ];

    public static function icon(string $name): string
    {
        return self::ICONS[$name] ?? '';
    }

    /**
     * What the bell shows: active reservations for customers, waiting pickups for shops,
     * shops to verify for admins. Returns [link, count].
     */
    public static function bell(?array $user): array
    {
        if (!$user) {
            return [route('login'), 0];
        }
        try {
            return match ($user['role']) {
                'admin' => [route('admin.home'), Shop::query()->where('is_verified', 0)->count()],
                'shop' => [route('shop.home'), (int) (Database::selectOne(
                    'SELECT COUNT(*) AS n FROM reservations r JOIN listings l ON l.id = r.listing_id JOIN shops s ON s.id = l.shop_id WHERE s.user_id = ? AND r.status = ?',
                    [$user['id'], 'reserved']
                )['n'] ?? 0)],
                default => [route('reservations.index'), Database::table('reservations')->where('user_id', $user['id'])->where('status', 'reserved')->count()],
            };
        } catch (\Throwable) {
            return [route('home'), 0]; // never let the bell break a page
        }
    }
}
