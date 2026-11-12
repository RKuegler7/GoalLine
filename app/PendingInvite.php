<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The league invite (or join code page) someone was on when they had to sign up, sign in or verify their email.
 * It's kept with their account for two weeks, so verifying on another device or after a while still brings them
 * back to the same join step, with the league goal ready to use.
 */
class PendingInvite
{
    public const DAYS = 14;

    public static function remember(User $user, ?string $url): void
    {
        if (filled($url)) {
            Cache::put(self::key($user), $url, now()->addDays(self::DAYS));
        }
    }

    public static function url(User $user): ?string
    {
        return Cache::get(self::key($user));
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    private static function key(User $user): string
    {
        return "pending-invite:{$user->id}";
    }
}
