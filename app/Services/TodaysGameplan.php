<?php

namespace App\Services;

use App\Clock;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Today's Gameplan: across every active goal, the tasks planned for today (plus any left over from
 * earlier in the GoalWeek) and the habits due today, on the person's own clock.
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
     * @param  Collection<int, Goal>  $goals  Active goals, ideally with weeks.tasks and habits.logs loaded.
     */
    public function __construct(Collection $goals)
    {
        $this->today = Clock::today();

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
        return $this->entries->sum(fn (array $entry) => $entry['tasks']->count() + $entry['habits']->count());
    }

    /**
     * How many of them are done.
     */
    public function done(): int
    {
        return $this->entries->sum(fn (array $entry) => $entry['tasks']->filter->completed_at->count()
            + $entry['habits']->filter(fn (Habit $habit) => $habit->isDoneOn($this->today))->count());
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

    private function isToday(CarbonInterface $moment): bool
    {
        return $moment->toDateString() === $this->today->toDateString();
    }
}
