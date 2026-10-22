<?php

namespace App\Models;

use App\Clock;
use Carbon\CarbonInterface;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A short shared challenge, like "No sugar for 30 days" or "Run 20 days this month". Members check in once a
 * day; finishing means checking in on at least the target number of days.
 */
#[Fillable(['title', 'description', 'starts_on', 'ends_on', 'target_days'])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    /**
     * How many days a challenge can run.
     *
     * @var list<int>
     */
    public const LENGTHS = [7, 14, 21, 30];

    /**
     * Check-ins stay open one extra day, for a day that ended before you logged it.
     */
    public const CHECK_IN_GRACE_DAYS = 1;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        static::creating(function (Challenge $challenge) {
            $challenge->invite_code ??= self::uniqueInviteCode();
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
            'target_days' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return HasMany<ChallengeMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChallengeMember::class)->orderBy('id');
    }

    /**
     * @return HasMany<ChallengeCheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(ChallengeCheckIn::class);
    }

    public function memberFor(User $user): ?ChallengeMember
    {
        return $this->members->firstWhere('user_id', $user->id);
    }

    public function lengthInDays(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    public function hasStarted(): bool
    {
        return Clock::today()->gte($this->starts_on);
    }

    public function isOver(): bool
    {
        return Clock::today()->gt($this->ends_on);
    }

    public function isActive(): bool
    {
        return $this->hasStarted() && ! $this->isOver();
    }

    /**
     * Which day of the challenge today is, from 1.
     */
    public function dayNumber(): int
    {
        return min($this->lengthInDays(), (int) $this->starts_on->diffInDays(Clock::today()) + 1);
    }

    /**
     * Whether a member can check in (or undo) for a day: a challenge day that's today or, within the grace, just before.
     */
    public function isCheckableOn(CarbonInterface $day): bool
    {
        $today = Clock::today();

        return $day->between($this->starts_on, $this->ends_on)
            && $day->lte($today)
            && $day->gte($today->copy()->subDays(self::CHECK_IN_GRACE_DAYS));
    }

    /**
     * Whether someone checked in on a day.
     */
    public function isCheckedIn(User $user, CarbonInterface $day): bool
    {
        return $this->checkIns->contains(fn (ChallengeCheckIn $checkIn) => $checkIn->user_id === $user->id && $checkIn->checked_on->isSameDay($day));
    }

    /**
     * The leaderboard: days checked in, then the current run, then who joined first.
     *
     * @return Collection<int, array{member: ChallengeMember, days: int, run: int, finished: bool, rank: int}>
     */
    public function standings(): Collection
    {
        $byUser = $this->checkIns->groupBy('user_id');
        $lastDay = Clock::today()->min($this->ends_on)->copy();

        $rows = $this->members->map(function (ChallengeMember $member) use ($byUser, $lastDay) {
            $days = $byUser->get($member->user_id, collect())->map(fn (ChallengeCheckIn $checkIn) => $checkIn->checked_on->toDateString())->unique();

            // The run counts back from today, or from yesterday if today isn't checked in yet.
            $day = $lastDay->copy();
            if (! $days->contains($day->toDateString())) {
                $day->subDay();
            }
            $run = 0;
            while ($days->contains($day->toDateString())) {
                $run++;
                $day->subDay();
            }

            return ['member' => $member, 'days' => $days->count(), 'run' => $run, 'finished' => $days->count() >= $this->target_days];
        });

        $sorted = $rows->sort(fn (array $a, array $b) => [$b['days'], $b['run'], $a['member']->id] <=> [$a['days'], $a['run'], $b['member']->id])->values();

        // Ties share a rank.
        $rank = 0;
        $previous = null;

        return $sorted->map(function (array $row, int $index) use (&$rank, &$previous) {
            $key = $row['days'].'-'.$row['run'];
            $rank = $key === $previous ? $rank : $index + 1;
            $previous = $key;

            return [...$row, 'rank' => $rank];
        });
    }

    public function joinUrl(): string
    {
        return route('challenges.join', $this->invite_code);
    }

    public static function uniqueInviteCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])->implode('');
            $code = substr($code, 0, 4).'-'.substr($code, 4);
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * Find a challenge by its code, ignoring case, spaces, and the dash.
     */
    public static function findByCode(string $code): ?self
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return strlen($code) === 8 ? self::where('invite_code', substr($code, 0, 4).'-'.substr($code, 4))->first() : null;
    }
}
