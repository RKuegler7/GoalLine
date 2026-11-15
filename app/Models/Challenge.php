<?php

namespace App\Models;

use App\Services\ChallengeProgress;
use App\ShareLink;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A Challenge: a shared run of days (or weeks) with one simple rule, scored by completion (ChallengeProgress), never
 * stars. Four types: Daily (a checklist every day), Weekly (X times a week or chosen days), Abstain (stay off
 * something) and Target (hit a number). Strict or flexible mode, optional Sundays off. Kickoff is 12:00 a.m. New York
 * time on the start date, and the rules lock then (only the name, description and visibility can change after).
 */
#[Fillable(['title', 'description', 'starts_on', 'ends_on', 'target_days', 'type', 'rules', 'mode', 'allowed_misses', 'sundays_off', 'template', 'is_public', 'max_members', 'league_id'])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const ABSTAIN = 'abstain';

    public const TARGET = 'target';

    public const TYPES = [
        self::DAILY => ['label' => 'Daily', 'explain' => 'Do it every day. A day counts when every rule is checked.'],
        self::WEEKLY => ['label' => 'Weekly', 'explain' => 'Do it a set number of times a week, or on chosen days. A week counts when the target is met.'],
        self::ABSTAIN => ['label' => 'Abstain', 'explain' => 'Stay off something. A day counts when you check in "Stayed on track".'],
        self::TARGET => ['label' => 'Target', 'explain' => 'Hit a number each day or each week, like 10,000 steps or 20 pages.'],
    ];

    public const STRICT = 'strict';

    public const FLEXIBLE = 'flexible';

    /**
     * Kickoff and the check-in day follow New York time, the same for everyone in the Challenge.
     */
    public const TIMEZONE = 'America/New_York';

    public const MAX_DAYS = 400;

    public const MAX_TITLE = 60;

    public const MAX_MISSES = 10;

    public const MAX_MEMBERS = 500;

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
            'rules' => 'array',
            'allowed_misses' => 'integer',
            'sundays_off' => 'boolean',
            'is_public' => 'boolean',
            'max_members' => 'integer',
        ];
    }

    /**
     * Today in New York, as midnight on the app clock so it compares cleanly with date columns.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(now(self::TIMEZONE)->toDateString(), config('app.timezone'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * The GoalLeague this is a side Challenge of, if any.
     *
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
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

    /**
     * @return HasMany<ChallengeInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(ChallengeInvite::class);
    }

    /**
     * Public Challenges anyone can find and join.
     *
     * @param  Builder<Challenge>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true)->whereNull('league_id');
    }

    public function memberFor(User $user): ?ChallengeMember
    {
        return $this->members->firstWhere('user_id', $user->id);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? 'Daily';
    }

    public function typeExplanation(): string
    {
        return self::TYPES[$this->type]['explain'] ?? self::TYPES[self::DAILY]['explain'];
    }

    /**
     * Whether a check-in is one tap: Weekly, Abstain ("on track"), or a Daily with one rule. A checklist or a number
     * needs the Challenge page.
     */
    public function isOneTap(): bool
    {
        return in_array($this->type, [self::WEEKLY, self::ABSTAIN], true) || ($this->type === self::DAILY && count($this->checklist()) === 1);
    }

    public function isStrict(): bool
    {
        return $this->mode === self::STRICT;
    }

    /**
     * Whether progress is counted in weeks: Weekly, or a Target per week.
     */
    public function isWeekly(): bool
    {
        return $this->type === self::WEEKLY || ($this->type === self::TARGET && (($this->rules ?? [])['per'] ?? 'day') === 'week');
    }

    /**
     * The checklist for a Daily Challenge (one rule if none were set).
     *
     * @return list<string>
     */
    public function checklist(): array
    {
        $items = array_values(array_filter(($this->rules ?? [])['items'] ?? [], fn ($item) => filled($item)));

        return $items === [] ? [$this->title] : $items;
    }

    /**
     * Whether a check-in completes its day: every checklist rule (Daily), on track (Abstain), the number reached
     * (Target per day), or simply done (Weekly; the week adds them up).
     */
    public function checkInCompletes(ChallengeCheckIn $checkIn): bool
    {
        if ($checkIn->status !== ChallengeCheckIn::DONE) {
            return false;
        }

        return match ($this->type) {
            self::DAILY => count(array_unique($checkIn->rules_done ?? [])) >= count($this->checklist()) || ($checkIn->rules_done === null && count($this->checklist()) === 1),
            self::TARGET => $checkIn->value !== null && (float) $checkIn->value >= (float) (($this->rules ?? [])['threshold'] ?? 0),
            default => true,
        };
    }

    /**
     * The rule in one line: "Every day: 2 rules", "4 times a week", "No sweets", "10,000 steps a day".
     */
    public function ruleSummary(): string
    {
        $rules = $this->rules ?? [];

        return match ($this->type) {
            self::WEEKLY => (filled($rules['label'] ?? null) ? $rules['label'].' ' : '').(! empty($rules['days'])
                ? 'on '.collect($rules['days'])->sort()->map(fn ($day) => Habit::DAY_NAMES[(int) $day] ?? '')->implode(', ')
                : ($rules['times'] ?? 1).' '.(((int) ($rules['times'] ?? 1)) === 1 ? 'time' : 'times').' a week'),
            self::ABSTAIN => $rules['label'] ?? $this->title,
            self::TARGET => number_format((float) ($rules['threshold'] ?? 0)).' '.($rules['unit'] ?? '').' a '.(($rules['per'] ?? 'day') === 'week' ? 'week' : 'day'),
            default => count($this->checklist()) === 1 ? $this->checklist()[0].', every day' : count($this->checklist()).' rules, every day',
        };
    }

    public function modeLabel(): string
    {
        return $this->isStrict()
            ? 'Strict: a missed '.($this->isWeekly() ? 'week' : 'day').' restarts your streak'
            : ($this->allowed_misses > 0
                ? 'Flexible: '.$this->allowed_misses.' '.($this->allowed_misses === 1 ? 'miss' : 'misses').' allowed'
                : 'Flexible: no misses allowed, but a miss never resets your progress');
    }

    public function lengthInDays(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    public function hasStarted(): bool
    {
        return self::today()->gte($this->starts_on);
    }

    public function isOver(): bool
    {
        return self::today()->gt($this->ends_on);
    }

    public function isActive(): bool
    {
        return $this->hasStarted() && ! $this->isOver();
    }

    /**
     * The rules lock at kickoff, like a GoalLeague plan.
     */
    public function rulesLocked(): bool
    {
        return $this->hasStarted();
    }

    public function dayNumber(): int
    {
        return max(1, min($this->lengthInDays(), (int) $this->starts_on->diffInDays(self::today()) + 1));
    }

    public function isFull(): bool
    {
        return $this->max_members !== null && $this->members()->count() >= $this->max_members;
    }

    /**
     * Whether a day can be checked in: inside the Challenge, not in the future, and today or yesterday.
     */
    public function isCheckableOn(CarbonInterface $day): bool
    {
        $today = self::today();

        return $day->between($this->starts_on, $this->ends_on)
            && $day->lte($today)
            && $day->gte($today->subDays(self::CHECK_IN_GRACE_DAYS));
    }

    public function isCheckedIn(User $user, CarbonInterface $day): bool
    {
        return $this->checkIns->contains(fn (ChallengeCheckIn $checkIn) => $checkIn->user_id === $user->id
            && $checkIn->checked_on->isSameDay($day)
            && $this->checkInCompletes($checkIn));
    }

    /**
     * One member's check-in on a day, if any.
     */
    public function checkInFor(User $user, CarbonInterface $day): ?ChallengeCheckIn
    {
        return $this->checkIns->first(fn (ChallengeCheckIn $checkIn) => $checkIn->user_id === $user->id && $checkIn->checked_on->isSameDay($day));
    }

    /**
     * One member's progress (see ChallengeProgress).
     *
     * @return array<string, mixed>
     */
    public function progressFor(int $userId): array
    {
        return ChallengeProgress::for($this, $this->checkIns->where('user_id', $userId)->values());
    }

    /**
     * The Standings, in the official order: completion %, then the current streak, then the completed count. Members
     * level on all three share a place.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(): Collection
    {
        $rows = $this->members->map(function (ChallengeMember $member) {
            $progress = $this->progressFor($member->user_id);

            return [
                'member' => $member,
                ...$progress,
                // Kept for older callers: completed periods and the current streak.
                'days' => $progress['completed'],
                'run' => $progress['streak'],
            ];
        });

        $sorted = $rows->sort(fn (array $a, array $b) => [$b['percent'], $b['streak'], $b['completed'], $a['member']->id]
            <=> [$a['percent'], $a['streak'], $a['completed'], $b['member']->id])->values();

        $rank = 0;
        $previous = null;

        return $sorted->map(function (array $row, int $index) use (&$rank, &$previous) {
            $key = $row['percent'].'-'.$row['streak'].'-'.$row['completed'];
            $rank = $key === $previous ? $rank : $index + 1;
            $previous = $key;

            return [...$row, 'rank' => $rank];
        });
    }

    /**
     * How many periods (days or weeks) a member needs in all, from the rules and dates alone.
     */
    public function totalPeriods(): int
    {
        return ChallengeProgress::for($this, collect(), CarbonImmutable::parse($this->starts_on->toDateString())->subDay())['total'];
    }

    public function joinUrl(): string
    {
        return ShareLink::route('challenges.join', $this->invite_code);
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

    /**
     * For a date picked in a form: that calendar day as midnight on the app clock.
     */
    public static function day(string $date): Carbon
    {
        return Carbon::parse(CarbonImmutable::parse($date)->toDateString(), config('app.timezone'));
    }
}
