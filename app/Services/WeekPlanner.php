<?php

namespace App\Services;

use App\Clock;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Carbon\CarbonInterface;

class WeekPlanner
{
    /**
     * Give every unscheduled, unfinished task in a GoalWeek a day, spreading them evenly from today (or the
     * week's first day) to its last, always filling the lightest day next. Tasks already on a day stay put.
     *
     * @return int How many tasks were scheduled.
     */
    public function autoPlan(RoadmapWeek $week): int
    {
        $days = $this->openDays($week);

        if ($days === []) {
            return 0;
        }

        $load = collect($days)->mapWithKeys(fn (CarbonInterface $day) => [
            $day->toDateString() => $week->tasks->filter(fn (RoadmapTask $task) => $task->planned_on?->isSameDay($day))->count(),
        ])->all();

        $scheduled = 0;

        foreach ($week->tasks->whereNull('planned_on')->whereNull('completed_at')->sortBy('position') as $task) {
            // The lightest day; ties go to the earliest.
            $lightest = array_search(min($load), $load, true);
            $task->update(['planned_on' => $lightest]);
            $load[$lightest]++;
            $scheduled++;
        }

        return $scheduled;
    }

    /**
     * The days still ahead in a GoalWeek, starting today if the week is underway.
     *
     * @return list<CarbonInterface>
     */
    public function openDays(RoadmapWeek $week): array
    {
        $from = $week->starts_on->max(Clock::today())->copy();
        $days = [];

        for ($day = $from; $day->lte($week->ends_on); $day = $day->copy()->addDay()) {
            $days[] = $day;
        }

        return $days;
    }
}
