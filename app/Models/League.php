<?php

namespace App\Models;

use App\Clock;
use App\GoalCategory;
use App\LeagueStatus;
use Carbon\CarbonInterface;
use Database\Factories\LeagueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'category', 'starts_on', 'ends_on'])]
class League extends Model
{
    /** @use HasFactory<LeagueFactory> */
    use HasFactory;

    /**
     * The most members a league can hold.
     */
    public const MAX_MEMBERS = 64;

    /**
     * The size we suggest, so everyone knows each other.
     */
    public const RECOMMENDED_MEMBERS = 12;

    /**
     * The longest season, in weeks.
     */
    public const MAX_WEEKS = 104;

    /**
     * Invite code characters, leaving out ones that are easy to confuse (0/O, 1/I).
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Give every league a short invite code that is easy to read aloud.
     */
    protected static function booted(): void
    {
        static::creating(function (League $league) {
            $league->invite_code ??= self::uniqueInviteCode();
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
            'starts_on' => 'date',
            'ends_on' => 'date',
            'category' => GoalCategory::class,
        ];
    }

    /**
     * The member who created the league.
     *
     * @return BelongsTo<User, $this>
     */
    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_id');
    }

    /**
     * Everyone in the league, in the order they joined.
     *
     * @return HasMany<LeagueMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(LeagueMember::class)->orderBy('id')->chaperone();
    }

    /**
     * Season awards won in this league.
     *
     * @return HasMany<UserBadge, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * Settled weekly results, one row per member per week.
     *
     * @return HasMany<LeagueResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(LeagueResult::class);
    }

    /**
     * Where the season stands today.
     */
    public function status(): LeagueStatus
    {
        return match (true) {
            Clock::today()->lt($this->starts_on) => LeagueStatus::Forming,
            Clock::today()->gt($this->ends_on) => LeagueStatus::Finished,
            default => LeagueStatus::Active,
        };
    }

    /**
     * How many league weeks the season has.
     */
    public function weeksCount(): int
    {
        return intdiv((int) $this->starts_on->diffInDays($this->ends_on), 7) + 1;
    }

    /**
     * The Monday a league week begins.
     */
    public function weekStartsOn(int $week): CarbonInterface
    {
        return $this->starts_on->copy()->addWeeks($week - 1);
    }

    /**
     * The Sunday a league week ends. Each member's GoalWeek containing this day is scored.
     */
    public function weekEndsOn(int $week): CarbonInterface
    {
        return $this->weekStartsOn($week)->addDays(6);
    }

    /**
     * The league week that includes a given day, or null outside the season.
     */
    public function weekNumberOn(CarbonInterface $day): ?int
    {
        if ($day->lt($this->starts_on) || $day->gt($this->ends_on)) {
            return null;
        }

        return intdiv((int) $this->starts_on->diffInDays($day), 7) + 1;
    }

    /**
     * The league week that includes today, if the season is underway.
     */
    public function currentWeek(): ?int
    {
        return $this->status() === LeagueStatus::Active
            ? intdiv((int) $this->starts_on->diffInDays(Clock::today()), 7) + 1
            : null;
    }

    /**
     * Whether people can still join: before kickoff and while there is room.
     */
    public function isJoinable(): bool
    {
        return $this->status() === LeagueStatus::Forming && $this->members->count() < self::MAX_MEMBERS;
    }

    /**
     * The membership for a user, if they belong to this league.
     */
    public function memberFor(User $user): ?LeagueMember
    {
        return $this->members->firstWhere('user_id', $user->id);
    }

    /**
     * An unused invite code like "K7QX-M4PA", without easily confused characters.
     */
    public static function uniqueInviteCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])->implode('');
            $code = substr($code, 0, 4).'-'.substr($code, 4);
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * The size below which the league page keeps an "Invite friends" button front and center.
     */
    public const INVITE_PROMPT_BELOW = 4;

    /**
     * Invites into this league.
     *
     * @return HasMany<LeagueInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(LeagueInvite::class);
    }

    /**
     * The league's current shareable link, made the first time it's needed.
     */
    public function shareableInvite(): LeagueInvite
    {
        return $this->invites()->usable()->where('kind', LeagueInvite::LINK)->latest('id')->first()
            ?? $this->invites()->create(['kind' => LeagueInvite::LINK, 'expires_at' => now()->addDays(LeagueInvite::DEFAULT_EXPIRY_DAYS)]);
    }

    /**
     * Invite codes are read aloud and typed by hand: ignore case, spaces, and a missing dash.
     */
    public static function normalizeInviteCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return strlen($code) === 8 ? substr($code, 0, 4).'-'.substr($code, 4) : $code;
    }

    /**
     * Make a new short invite code, so the old one stops working.
     */
    public function regenerateInviteCode(): void
    {
        $this->forceFill(['invite_code' => self::uniqueInviteCode()])->save();
    }

    /**
     * Group tasks proposed in this league.
     *
     * @return HasMany<GroupTask, $this>
     */
    public function groupTasks(): HasMany
    {
        return $this->hasMany(GroupTask::class);
    }

    /**
     * Chat messages and league updates, oldest first.
     *
     * @return HasMany<LeagueMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(LeagueMessage::class)->orderBy('id');
    }

    /**
     * Post a league update in the chat, like a member joining or a week going final.
     */
    public function announce(string $body): LeagueMessage
    {
        return $this->messages()->create(['body' => $body, 'is_system' => true]);
    }
}
