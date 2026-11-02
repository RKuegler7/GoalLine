<?php

namespace App\Services;

use App\Clock;
use App\Models\ChallengeCheckIn;
use App\Models\HabitLog;
use App\Models\RoadmapTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The daily streak: consecutive days, on the member's clock, with at least one check-in (a task, a habit,
 * or a challenge).
 * A streak stays alive through today until midnight, so it's "at risk" until today has a check-in.
 */
class DailyStreak
{
    /**
     * How far back to look. Streaks longer than this are shown as this long.
     */
    public const LOOKBACK_DAYS = 400;

    /**
     * Local dates (Y-m-d) with at least one check-in.
     *
     * @var Collection<string, true>
     */
    private Collection $activeDays;

    public function __construct(public User $user)
    {
        $since = Clock::today()->subDays(self::LOOKBACK_DAYS);

        $taskDays = RoadmapTask::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since->copy()->subDay())
            ->whereHas('week.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->pluck('completed_at')
            ->map(fn ($moment) => Clock::local($moment)->toDateString());

        $habitDays = HabitLog::query()
            ->where('met', true)
            ->whereDate('logged_on', '>=', $since->toDateString())
            ->whereHas('habit.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->get()
            ->map(fn (HabitLog $log) => $log->logged_on->toDateString());

        $challengeDays = ChallengeCheckIn::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', '>=', $since->toDateString())
            ->get()
            ->map(fn (ChallengeCheckIn $checkIn) => $checkIn->checked_on->toDateString());

        $this->activeDays = $taskDays->merge($habitDays)->merge($challengeDays)->unique()->mapWithKeys(fn (string $day) => [$day => true]);
    }

    /**
     * Days in a row, counting today if there's a check-in, otherwise ending yesterday.
     */
    public function current(): int
    {
        $day = Clock::today();

        if (! $this->checkedInToday()) {
            $day->subDay();
        }

        $streak = 0;

        while ($this->activeDays->has($day->toDateString()) && $streak < self::LOOKBACK_DAYS) {
            $streak++;
            $day->subDay();
        }

        return $streak;
    }

    /**
     * Whether there's a check-in today yet.
     */
    public function checkedInToday(): bool
    {
        return $this->activeDays->has(Clock::today()->toDateString());
    }

    /**
     * This calendar week, Monday to Sunday, day by day: whether each day so far had a check-in. Days still ahead
     * are null.
     *
     * @return array<string, bool|null> Keyed by local date.
     */
    public function thisWeek(): array
    {
        $today = Clock::today();
        $days = [];

        for ($day = $today->copy()->startOfWeek(CarbonInterface::MONDAY); $days === [] || count($days) < 7; $day = $day->copy()->addDay()) {
            $days[$day->toDateString()] = $day->gt($today) ? null : $this->activeDays->has($day->toDateString());
        }

        return $days;
    }

    /**
     * Days on the line this week: days so far with at least one check-in.
     */
    public function daysOnTheLineThisWeek(): int
    {
        return count(array_filter($this->thisWeek()));
    }

    /**
     * A streak that ends tonight unless the member checks in today.
     */
    public function isAtRisk(): bool
    {
        return ! $this->checkedInToday() && $this->current() > 0;
    }
}
