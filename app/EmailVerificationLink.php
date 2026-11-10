<?php

namespace App;

use App\Models\User;

/**
 * Email verification links that survive the trip from inbox to app. Laravel's standard link carries its expiry and
 * signature in the query string (?expires=…&signature=…), which a CDN cache policy or an email click-tracker can drop
 * or re-encode, turning a good link into "403 Invalid signature". These links put both in the path instead, are built
 * from APP_URL (https in production), and are checked with a constant-time comparison against the app key.
 */
final class EmailVerificationLink
{
    public const VALID = 'valid';

    public const EXPIRED = 'expired';

    public const INVALID = 'invalid';

    /**
     * The link for this person's verification email.
     */
    public static function for(User $user): string
    {
        $expires = now()->addHours((int) config('goalline.verification_link_hours', 48))->getTimestamp();
        $hash = sha1($user->getEmailForVerification());

        return ShareLink::route('verification.confirm', [
            'id' => $user->getKey(),
            'hash' => $hash,
            'expires' => $expires,
            'signature' => self::sign($user->getKey(), $hash, $expires),
        ]);
    }

    /**
     * Whether a link's parts are genuine and still in date. The person and their email are checked separately.
     */
    public static function check(int|string $id, string $hash, int|string $expires, string $signature): string
    {
        if (! ctype_digit((string) $expires) || ! hash_equals(self::sign($id, $hash, (int) $expires), $signature)) {
            return self::INVALID;
        }

        return (int) $expires < now()->getTimestamp() ? self::EXPIRED : self::VALID;
    }

    private static function sign(int|string $id, string $hash, int $expires): string
    {
        return hash_hmac('sha256', "verify-email|{$id}|{$hash}|{$expires}", (string) config('app.key'));
    }
}
