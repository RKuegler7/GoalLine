<?php

namespace App\Services;

use App\GoalStatus;
use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ProfileStats
{
    /**
     * Every goal the person has set, with roadmaps loaded.
     *
     * @var EloquentCollection<int, Goal>
     */
    public EloquentCollection $goals;

    /**
     * Finished (locked) GoalWeeks across all goals, oldest first.
     *
     * @var Collection<int, RoadmapWeek>
     */
    public Collection $finishedWeeks;

    public function __construct(public User $user)
    {
        $this->goals = $user->goals()->with('weeks.tasks', 'habits.logs')->latest()->get();

        $this->finishedWeeks = $this->goals
            ->flatMap(fn (Goal $goal) => $goal->weeks)
            ->filter(fn (RoadmapWeek $week) => $week->isLocked())
            ->sortBy(fn (RoadmapWeek $week) => $week->ends_on->timestamp)
            ->values();
    }

    /**
     * All GoalWeek result points earned, including GoalWeeks still in progress.
     */
    public function points(): int
    {
        return $this->goals->sum(fn (Goal $goal) => $goal->points());
    }

    /**
     * How many goals the person has set.
     */
    public function goalsStarted(): int
    {
        return $this->goals->count();
    }

    /**
     * Goals the person crossed the line on, newest first.
     *
     * @return Collection<int, Goal>
     */
    public function completedGoals(): Collection
    {
        return $this->goals->where('status', GoalStatus::Completed)->sortByDesc('completed_at')->values();
    }

    /**
     * Goals still being pursued.
     *
     * @return Collection<int, Goal>
     */
    public function activeGoals(): Collection
    {
        return $this->goals->where('status', GoalStatus::Active)->sortBy('target_date')->values();
    }

    /**
     * How many finished GoalWeeks landed on each result.
     *
     * @return array<string, int>
     */
    public function resultCounts(): array
    {
        return collect(GoalWeekResult::cases())
            ->mapWithKeys(fn (GoalWeekResult $tier) => [$tier->value => $this->finishedWeeks->filter(fn (RoadmapWeek $week) => $week->result() === $tier)->count()])
            ->all();
    }

    /**
     * Consecutive finished GoalWeeks at In line or better, counting back from the latest.
     */
    public function currentStreak(): int
    {
        $streak = 0;

        foreach ($this->finishedWeeks->reverse() as $week) {
            if ($week->result() === GoalWeekResult::Short) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /**
     * The longest run of finished GoalWeeks at In line or better.
     */
    public function bestStreak(): int
    {
        $best = $run = 0;

        foreach ($this->finishedWeeks as $week) {
            $run = $week->result() === GoalWeekResult::Short ? 0 : $run + 1;
            $best = max($best, $run);
        }

        return $best;
    }

    /**
     * The most recent finished GoalWeek results, oldest first, like a league form guide.
     *
     * @return Collection<int, RoadmapWeek>
     */
    public function form(int $count = 10): Collection
    {
        return $this->finishedWeeks->slice(-$count)->values();
    }

    /**
     * Overall league record across every league: wins, ties, losses.
     *
     * @return array{wins: int, ties: int, losses: int}
     */
    public function leagueRecord(): array
    {
        $outcomes = $this->user->leagueResults()->get()->countBy(fn ($result) => $result->outcome->value);

        return [
            'wins' => $outcomes->get(MatchupOutcome::Win->value, 0),
            'ties' => $outcomes->get(MatchupOutcome::Tie->value, 0),
            'losses' => $outcomes->get(MatchupOutcome::Loss->value, 0),
        ];
    }

    /**
     * The leagues the person plays in, newest season first.
     *
     * @return Collection<int, League>
     */
    public function leagues(): Collection
    {
        return $this->user->leagues()->withCount('members')->latest('starts_on')->get();
    }
}
