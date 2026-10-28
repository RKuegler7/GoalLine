<?php

namespace App\Models;

use Database\Factories\LeagueInviteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invite into a GoalLeague: a shareable link anyone can use (optionally expiring or capped), or a personal invite
 * sent to an email address or a member's username. Tokens are long and random, so they can't be guessed.
 */
#[Fillable(['kind', 'email', 'invitee_id', 'expires_at', 'max_uses'])]
class LeagueInvite extends Model
{
    /** @use HasFactory<LeagueInviteFactory> */
    use HasFactory;

    public const LINK = 'link';

    public const EMAIL = 'email';

    public const USERNAME = 'username';

    /**
     * Invites expire after this many days unless the owner picks otherwise.
     */
    public const DEFAULT_EXPIRY_DAYS = 14;

    /**
     * Expiry choices for a shareable link, in days (null never expires).
     */
    public const EXPIRY_CHOICES = [1, 7, 14, 30, null];

    protected static function booted(): void
    {
        static::creating(function (LeagueInvite $invite) {
            $invite->token ??= Str::random(40);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'accepted_at' => 'datetime',
            'max_uses' => 'integer',
            'uses' => 'integer',
        ];
    }

    /**
     * Invites that can still be used: not revoked, not expired, not used up, and, for personal ones, not yet accepted.
     *
     * @param  Builder<LeagueInvite>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $uses) => $uses->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'));
    }

    /**
     * The usable invite with this token, if there is one.
     */
    public static function findUsable(?string $token): ?self
    {
        return $token ? self::usable()->where('token', $token)->with('league')->first() : null;
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->accepted_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->uses < $this->max_uses);
    }

    /**
     * Whether it's for one person (by email or username) rather than a link to share.
     */
    public function isPersonal(): bool
    {
        return $this->kind !== self::LINK;
    }

    /**
     * Use up one use, atomically, so a capped link can't go past its limit. Personal invites are marked accepted.
     */
    public function claim(): bool
    {
        $claimed = self::usable()->whereKey($this->id)->increment('uses') === 1;

        if ($claimed && $this->isPersonal()) {
            $this->forceFill(['accepted_at' => now()])->save();
        }

        return $claimed;
    }

    public function url(): string
    {
        return route('leagues.invite.show', $this->token);
    }

    /**
     * Who it's for, as shown to the league owner. Never shown to anyone else.
     */
    public function recipientLabel(): string
    {
        return match ($this->kind) {
            self::EMAIL => (string) $this->email,
            self::USERNAME => '@'.$this->invitee?->username,
            default => 'Shareable link',
        };
    }
}
