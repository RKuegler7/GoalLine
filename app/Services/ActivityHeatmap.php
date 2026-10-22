<?php

namespace App\Services;

use App\Clock;
use App\Models\ChallengeCheckIn;
use App\Models\HabitLog;
use App\Models\RoadmapTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A year of check-ins, one square per day on the member's clock: tasks checked off, habits logged, and
 * challenge check-ins.
 */
class ActivityHeatmap
{
    public const WEEKS = 53;

    /**
     * Check-ins per local date (Y-m-d).
     *
     * @var Collection<string, int>
     */
    private Collection $counts;

    public readonly CarbonImmutable $today;

    public readonly CarbonImmutable $start;

    public function __construct(public User $user)
    {
        $this->today = CarbonImmutable::parse(Clock::today()->toDateString());
        // Whole weeks, Monday to Sunday, ending with this one.
        $this->start = $this->today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(self::WEEKS - 1);

        $taskDays = RoadmapTask::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $this->start->subDay())
            ->whereHas('week.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->pluck('completed_at')
            ->map(fn ($moment) => Clock::local($moment)->toDateString());

        $habitDays = HabitLog::query()
            ->whereDate('logged_on', '>=', $this->start->toDateString())
            ->whereHas('habit.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->get()
            ->map(fn (HabitLog $log) => $log->logged_on->toDateString());

        $challengeDays = ChallengeCheckIn::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', '>=', $this->start->toDateString())
            ->get()
            ->map(fn (ChallengeCheckIn $checkIn) => $checkIn->checked_on->toDateString());

        $this->counts = $taskDays->merge($habitDays)->merge($challengeDays)->countBy();
    }

    /**
     * Columns of weeks, each with seven days from Monday. Days after today are null.
     *
     * @return list<list<array{date: CarbonImmutable, count: int, level: int}|null>>
     */
    public function weeks(): array
    {
        return collect(range(0, self::WEEKS - 1))->map(function (int $week) {
            return collect(range(0, 6))->map(function (int $weekday) use ($week) {
                $date = $this->start->addWeeks($week)->addDays($weekday);

                if ($date->gt($this->today)) {
                    return null;
                }

                $count = $this->counts->get($date->toDateString(), 0);

                return ['date' => $date, 'count' => $count, 'level' => $this->level($count)];
            })->all();
        })->all();
    }

    /**
     * Total check-ins in the year shown.
     */
    public function total(): int
    {
        return (int) $this->counts->filter(fn (int $count, string $date) => $date >= $this->start->toDateString())->sum();
    }

    /**
     * Days with at least one check-in in the year shown.
     */
    public function activeDays(): int
    {
        return $this->counts->filter(fn (int $count, string $date) => $date >= $this->start->toDateString())->count();
    }

    /**
     * The longest run of consecutive active days in the year shown.
     */
    public function longestStreak(): int
    {
        $best = $run = 0;

        for ($date = $this->start; $date->lte($this->today); $date = $date->addDay()) {
            $run = $this->counts->has($date->toDateString()) ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return $best;
    }

    /**
     * Shade from 0 (none) to 4 (a big day).
     */
    private function level(int $count): int
    {
        return match (true) {
            $count === 0 => 0,
            $count === 1 => 1,
            $count <= 3 => 2,
            $count <= 5 => 3,
            default => 4,
        };
    }
}
