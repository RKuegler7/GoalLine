<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A private beta invite code.
 */
#[Fillable(['note', 'max_uses'])]
class Invite extends Model
{
    /**
     * Letters and digits that can't be confused with each other.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        static::creating(function (Invite $invite) {
            $invite->code ??= self::uniqueCode();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    /**
     * People who joined with this code.
     *
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Find a code that can still be used, ignoring case and spaces.
     */
    public static function usable(?string $code): ?self
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        if ($normalized === '') {
            return null;
        }

        return self::where('code', $normalized)->whereNull('revoked_at')->whereColumn('uses', '<', 'max_uses')->first();
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->uses < $this->max_uses;
    }

    public function signupUrl(): string
    {
        return route('register', ['invite' => $this->code]);
    }

    private static function uniqueCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
        } while (self::where('code', $code)->exists());

        return Str::upper($code);
    }
}
