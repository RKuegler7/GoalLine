<?php

namespace App\Services;

use App\Clock;
use App\GoalWeekResult;
use App\Models\ChallengeCheckIn;
use App\Models\Cheer;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Models\UserBadge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A member's week in GoalLine, Monday to Sunday on their clock: effort, results so far, streak, badges,
 * cheers, GoalLeague standing, and challenge progress.
 */
class WeeklyRecap
{
    public readonly CarbonImmutable $monday;

    public readonly CarbonImmutable $sunday;

    /**
     * Check-ins per local date (Y-m-d) this week.
     *
     * @var Collection<string, int>
     */
    public readonly Collection $daily;

    public function __construct(public User $user, ?CarbonImmutable $day = null)
    {
        $day ??= CarbonImmutable::parse(Clock::today()->toDateString());
        $this->monday = $day->startOfWeek(CarbonImmutable::MONDAY);
        $this->sunday = $this->monday->addDays(6);

        $from = $this->monday->toDateString();
        $to = $this->sunday->toDateString();

        $tasks = RoadmapTask::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $this->monday->subDay())
            ->where('completed_at', '<', $this->sunday->addDays(2))
            ->whereHas('week.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->pluck('completed_at')
            ->map(fn ($moment) => Clock::local($moment)->toDateString());

        $habits = HabitLog::query()
            ->whereDate('logged_on', '>=', $from)->whereDate('logged_on', '<=', $to)
            ->whereHas('habit.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->get()
            ->map(fn (HabitLog $log) => $log->logged_on->toDateString());

        $challenges = ChallengeCheckIn::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', '>=', $from)->whereDate('checked_on', '<=', $to)
            ->get()
            ->map(fn (ChallengeCheckIn $checkIn) => $checkIn->checked_on->toDateString());

        $this->daily = $tasks->merge($habits)->merge($challenges)
            ->filter(fn (string $date) => $date >= $from && $date <= $to)
            ->countBy();
    }

    public function checkIns(): int
    {
        return (int) $this->daily->sum();
    }

    public function activeDays(): int
    {
        return $this->daily->count();
    }

    /**
     * The day with the most check-ins, if any.
     *
     * @return array{date: CarbonImmutable, count: int}|null
     */
    public function bestDay(): ?array
    {
        if ($this->daily->isEmpty()) {
            return null;
        }

        $date = $this->daily->sortDesc()->keys()->first();

        return ['date' => CarbonImmutable::parse($date), 'count' => $this->daily[$date]];
    }

    /**
     * Each active goal's GoalWeek covering this week, with its live result.
     *
     * @return Collection<int, array{goal: Goal, week: RoadmapWeek, result: GoalWeekResult|null}>
     */
    public function goalWeeks(): Collection
    {
        return $this->user->goals()->active()->with(['weeks.tasks', 'habits.logs'])->get()
            ->map(function (Goal $goal) {
                $week = $goal->weeks->first(fn (RoadmapWeek $week) => $week->starts_on->lte($this->sunday) && $week->ends_on->gte($this->sunday));

                return $week ? ['goal' => $goal, 'week' => $week, 'result' => $week->result()] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, UserBadge>
     */
    public function badgesEarned(): Collection
    {
        return $this->user->badges()->whereBetween('earned_at', [$this->monday->startOfDay(), $this->sunday->endOfDay()])->get();
    }

    public function cheersReceived(): int
    {
        return Cheer::query()
            ->whereHas('highlight', fn ($highlights) => $highlights->where('user_id', $this->user->id))
            ->where('user_id', '!=', $this->user->id)
            ->whereBetween('created_at', [$this->monday->startOfDay(), $this->sunday->endOfDay()])
            ->count();
    }

    public function streak(): int
    {
        return (new DailyStreak($this->user))->current();
    }

    /**
     * The one-line summary used in the notification.
     */
    public function headline(): string
    {
        if ($this->checkIns() === 0) {
            return 'A quiet week. A fresh GoalWeek starts tomorrow: plan it and get back in line.';
        }

        $points = $this->goalWeeks()->sum(fn (array $row) => $row['result']?->points() ?? 0);

        return "Your week: {$this->checkIns()} ".str('check-in')->plural($this->checkIns())
            ." on {$this->activeDays()} ".str('day')->plural($this->activeDays())
            .", {$points} ".str('point')->plural($points)
            .($this->streak() > 1 ? ", and a {$this->streak()}-day streak." : '.');
    }
}
