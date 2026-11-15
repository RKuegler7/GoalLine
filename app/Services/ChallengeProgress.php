<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * How one member is doing in a Challenge, in plain tested code (no AI). Challenges are scored by completion, never
 * stars, and never touch GoalLeague standings.
 *
 * The Challenge is split into periods: days (Daily, Abstain, Target per day) or 7-day blocks from the start (Weekly,
 * Target per week). Each period is:
 * - done: Daily, every checklist rule ticked; Weekly, the target met (X times, or every chosen day); Abstain, checked
 *   "on track"; Target, the number reached;
 * - slipped: Abstain, "Slipped today";
 * - rest: Sundays off on a Sunday, or a week with none of the chosen days;
 * - open: today or yesterday (still checkable) and not done yet; it never breaks a streak;
 * - missed: everything else that's over;
 * - upcoming: not started yet.
 * Strict mode: every miss or slip restarts the streak (shown as a restart). Flexible mode: the first allowed_misses
 * misses are forgiven (they don't break the streak); after that a miss breaks it.
 */
class ChallengeProgress
{
    public const DONE = 'done';

    public const MISSED = 'missed';

    public const SLIPPED = 'slipped';

    public const REST = 'rest';

    public const OPEN = 'open';

    public const UPCOMING = 'upcoming';

    /**
     * @param  Collection<int, ChallengeCheckIn>  $checkIns  This member's check-ins in this Challenge.
     * @return array{periods: list<array{start: CarbonImmutable, end: CarbonImmutable, status: string, forgiven: bool, count: int, needed: int}>, unit: string, completed: int, total: int, percent: int, streak: int, bestStreak: int, restarts: int, missesUsed: int, last5: list<array{status: string, forgiven: bool, label: string}>, finished: bool, verified: int, thisPeriod: array{count: int, needed: int, status: string}|null}
     */
    public static function for(Challenge $challenge, Collection $checkIns, ?CarbonImmutable $today = null): array
    {
        $today ??= Challenge::today();
        $byDay = $checkIns->filter(fn (ChallengeCheckIn $checkIn) => $checkIn->status !== ChallengeCheckIn::VOIDED)
            ->keyBy(fn (ChallengeCheckIn $checkIn) => $checkIn->checked_on->toDateString());
        $weekly = $challenge->isWeekly();
        $periods = $weekly ? self::weeks($challenge, $byDay, $today) : self::days($challenge, $byDay, $today);

        // Mode: forgive the first allowed misses (flexible), or count every miss as a restart (strict).
        $allowance = $challenge->isStrict() ? 0 : (int) $challenge->allowed_misses;
        $used = 0;
        $restarts = 0;
        $streak = 0;
        $best = 0;

        foreach ($periods as $index => $period) {
            if (in_array($period['status'], [self::MISSED, self::SLIPPED], true)) {
                if ($used < $allowance) {
                    $used++;
                    $periods[$index]['forgiven'] = true;

                    continue;
                }
                $restarts += $streak > 0 || $challenge->isStrict() ? 1 : 0;
                $streak = 0;
            } elseif ($period['status'] === self::DONE) {
                $streak++;
                $best = max($best, $streak);
            }
        }

        $total = count(array_filter($periods, fn (array $period) => $period['status'] !== self::REST));
        $completed = count(array_filter($periods, fn (array $period) => $period['status'] === self::DONE));
        $closed = array_values(array_filter($periods, fn (array $period) => ! in_array($period['status'], [self::UPCOMING, self::OPEN], true)
            || $period['status'] === self::DONE));
        $current = array_values(array_filter($periods, fn (array $period) => $period['start']->lte($today) && $period['end']->gte($today)))[0] ?? null;

        return [
            'periods' => $periods,
            'unit' => $weekly ? 'week' : 'day',
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) floor($completed / $total * 100) : 0,
            'streak' => $streak,
            'bestStreak' => $best,
            'restarts' => $challenge->isStrict() ? $restarts : 0,
            'missesUsed' => $used,
            'last5' => array_map(fn (array $period) => [
                'status' => $period['status'],
                'forgiven' => $period['forgiven'],
                'label' => $weekly ? 'Week of '.$period['start']->format('M j') : $period['start']->format('D, M j'),
            ], array_slice($closed, -5)),
            'finished' => $total > 0 && $completed >= $total - $allowance,
            'verified' => $byDay->filter(fn (ChallengeCheckIn $checkIn) => $checkIn->isVerified())->count(),
            'thisPeriod' => $current ? ['count' => $current['count'], 'needed' => $current['needed'], 'status' => $current['status']] : null,
        ];
    }

    /**
     * One period per day, from the start to the end.
     *
     * @param  Collection<string, ChallengeCheckIn>  $byDay
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, status: string, forgiven: bool, count: int, needed: int}>
     */
    private static function days(Challenge $challenge, Collection $byDay, CarbonImmutable $today): array
    {
        $periods = [];
        $end = CarbonImmutable::parse($challenge->ends_on->toDateString());

        for ($day = CarbonImmutable::parse($challenge->starts_on->toDateString()); $day->lte($end); $day = $day->addDay()) {
            $checkIn = $byDay->get($day->toDateString());
            $done = $checkIn !== null && $challenge->checkInCompletes($checkIn);
            $status = match (true) {
                $challenge->sundays_off && $day->isSunday() => self::REST,
                $done => self::DONE,
                $day->gt($today) => self::UPCOMING,
                $checkIn?->status === ChallengeCheckIn::SLIPPED => self::SLIPPED,
                $day->gte($today->subDays(Challenge::CHECK_IN_GRACE_DAYS)) => self::OPEN,
                default => self::MISSED,
            };
            $periods[] = ['start' => $day, 'end' => $day, 'status' => $status, 'forgiven' => false, 'count' => $done ? 1 : 0, 'needed' => 1];
        }

        return $periods;
    }

    /**
     * One period per 7-day block from the start (the last one may be shorter). X times a week (capped at the days in
     * a short block), every chosen weekday in the block, or a weekly number total.
     *
     * @param  Collection<string, ChallengeCheckIn>  $byDay
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, status: string, forgiven: bool, count: int, needed: int}>
     */
    private static function weeks(Challenge $challenge, Collection $byDay, CarbonImmutable $today): array
    {
        $periods = [];
        $last = CarbonImmutable::parse($challenge->ends_on->toDateString());
        $rules = $challenge->rules ?? [];

        for ($start = CarbonImmutable::parse($challenge->starts_on->toDateString()); $start->lte($last); $start = $start->addDays(7)) {
            $end = $start->addDays(6)->min($last);
            $days = collect(range(0, (int) $start->diffInDays($end)))->map(fn (int $offset) => $start->addDays($offset));
            $inBlock = $days->map(fn (CarbonImmutable $day) => $byDay->get($day->toDateString()))->filter();

            if ($challenge->type === Challenge::TARGET) {
                $count = (int) round((float) $inBlock->sum(fn (ChallengeCheckIn $checkIn) => (float) $checkIn->value));
                $needed = (int) ($rules['threshold'] ?? 1);
            } elseif (! empty($rules['days'])) {
                $chosen = $days->filter(fn (CarbonImmutable $day) => in_array($day->dayOfWeekIso, $rules['days'], false));
                $needed = $chosen->count();
                $count = $chosen->filter(fn (CarbonImmutable $day) => $byDay->has($day->toDateString()))->count();
            } else {
                $needed = min((int) ($rules['times'] ?? 1), $days->count());
                $count = $inBlock->count();
            }

            $status = match (true) {
                $needed === 0 => self::REST,
                $count >= $needed => self::DONE,
                $start->gt($today) => self::UPCOMING,
                $end->gte($today->subDays(Challenge::CHECK_IN_GRACE_DAYS)) => self::OPEN,
                default => self::MISSED,
            };
            $periods[] = ['start' => $start, 'end' => $end, 'status' => $status, 'forgiven' => false, 'count' => $count, 'needed' => $needed];
        }

        return $periods;
    }
}
