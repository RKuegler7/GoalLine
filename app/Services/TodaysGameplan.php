<?php

namespace App\Services;

use App\Clock;
use App\Models\Challenge;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Today's Gameplan: across every active goal, the tasks planned for today (plus any left over from
 * earlier in the GoalWeek) and the habits due today, plus today's check-in for each running challenge,
 * on the person's own clock.
 */
class TodaysGameplan
{
    public readonly CarbonInterface $today;

    /**
     * One entry per goal with a GoalWeek open for check-ins.
     *
     * @var Collection<int, array{goal: Goal, week: RoadmapWeek, tasks: Collection<int, RoadmapTask>, habits: Collection<int, Habit>, unplanned: int}>
     */
    public readonly Collection $entries;

    /**
     * Challenges running today that the member is in, with their own check-ins loaded.
     *
     * @var Collection<int, Challenge>
     */
    public readonly Collection $challenges;

    /**
     * @param  Collection<int, Goal>  $goals  Active goals, ideally with weeks.tasks and habits.logs loaded.
     * @param  User|null  $member  Whose challenges to include.
     */
    public function __construct(Collection $goals, ?User $member = null)
    {
        $this->today = Clock::today();
        $this->challenges = $member
            ? $member->challenges()
                ->whereDate('starts_on', '<=', $this->today->toDateString())
                ->whereDate('ends_on', '>=', $this->today->toDateString())
                ->with(['checkIns' => fn ($checkIns) => $checkIns->where('user_id', $member->id)])
                ->orderBy('ends_on')
                ->get()
            : collect();

        $this->entries = $goals
            ->map(function (Goal $goal) {
                $week = $goal->currentWeek();

                if (! $week?->isCheckable()) {
                    return null;
                }

                return [
                    'goal' => $goal,
                    'week' => $week,
                    'tasks' => $week->tasks
                        ->filter(fn (RoadmapTask $task) => $task->completed_at
                            ? $this->isToday(Clock::local($task->completed_at))
                            : $task->planned_on?->lte($this->today))
                        ->sortBy(fn (RoadmapTask $task) => [$task->planned_on?->toDateString() ?? '', $task->position])
                        ->values(),
                    'habits' => $week->habits()
                        ->filter(fn (Habit $habit) => $habit->archived_at === null && $habit->isDueToday($week))
                        ->values(),
                    'unplanned' => $week->tasks->whereNull('planned_on')->whereNull('completed_at')->count(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Whether a task was planned for an earlier day and is still open.
     */
    public function isCarriedOver(RoadmapTask $task): bool
    {
        return ! $task->completed_at && $task->planned_on?->lt($this->today);
    }

    /**
     * How many things are on today's list.
     */
    public function total(): int
    {
        return $this->entries->sum(fn (array $entry) => $entry['tasks']->count() + $entry['habits']->count()) + $this->challenges->count();
    }

    /**
     * How many of them are done.
     */
    public function done(): int
    {
        return $this->entries->sum(fn (array $entry) => $entry['tasks']->filter->completed_at->count()
            + $entry['habits']->filter(fn (Habit $habit) => $habit->isDoneOn($this->today))->count())
            + $this->challenges->filter(fn (Challenge $challenge) => $this->isChallengeDone($challenge))->count();
    }

    /**
     * How many are left.
     */
    public function remaining(): int
    {
        return $this->total() - $this->done();
    }

    /**
     * Tasks this GoalWeek that aren't on a day yet.
     */
    public function unplanned(): int
    {
        return $this->entries->sum('unplanned');
    }

    /**
     * Whether tomorrow still falls inside the task's GoalWeek.
     */
    public function canMoveToTomorrow(RoadmapTask $task, RoadmapWeek $week): bool
    {
        return ! $task->completed_at && $this->today->copy()->addDay()->lte($week->ends_on);
    }

    /**
     * Whether today's challenge check-in is in.
     */
    public function isChallengeDone(Challenge $challenge): bool
    {
        return $challenge->checkIns->contains(fn ($checkIn) => $checkIn->checked_on->isSameDay($this->today));
    }

    private function isToday(CarbonInterface $moment): bool
    {
        return $moment->toDateString() === $this->today->toDateString();
    }
}
