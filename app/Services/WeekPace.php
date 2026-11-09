<?php

namespace App\Services;

use App\Clock;
use App\GoalWeekResult;
use App\Models\Habit;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Carbon\CarbonInterface;

/**
 * "Projected, on current pace": the band a GoalWeek is heading for if the rest of it goes the way it has so far.
 * Plain code, no AI. Pace is the points earned so far divided by the points due so far:
 * - a task planned for an earlier day is due; one planned for today or later is due once it's done;
 * - a task with no day, and each habit's weekly target, come due evenly across the week (or as they're done);
 * - the milestone is due once it's hit, or when the week is over.
 * Doing planned work early doesn't inflate the pace; bonus work and beating a task's number do.
 */
class WeekPace
{
    /**
     * The projected share of plan: earned so far over due so far. Null when nothing is due yet.
     */
    public static function share(float $earned, float $due): ?float
    {
        return $due > 0 ? $earned / $due : null;
    }

    /**
     * The band a projected share lands in. Unproven Smashed pace projects as Past, like the real result.
     */
    public static function band(?float $share, bool $smashVerified = true): ?GoalWeekResult
    {
        return $share === null ? null : GoalWeekResult::score($share, $smashVerified);
    }

    /**
     * How much of the GoalWeek has gone by, from 0 (it starts today or later) to 1 (it's over). Today doesn't count yet.
     */
    public static function elapsed(RoadmapWeek $week, ?CarbonInterface $today = null): float
    {
        $today ??= Clock::today();
        $days = (int) $week->starts_on->diffInDays($week->ends_on) + 1;
        $past = (int) $week->starts_on->diffInDays($today, false);

        return max(0.0, min(1.0, $past / $days));
    }

    /**
     * The planned points due so far.
     */
    public static function duePoints(RoadmapWeek $week, ?CarbonInterface $today = null): float
    {
        $today ??= Clock::today();
        $elapsed = self::elapsed($week, $today);

        $tasks = $week->tasks->reject->is_bonus->sum(function (RoadmapTask $task) use ($today, $elapsed) {
            $scheduled = $task->planned_on === null
                ? $task->points * $elapsed
                : ($task->planned_on->lt($today) ? $task->points : 0);

            return max($scheduled, $task->completed_at ? $task->points : 0);
        });

        $habits = $week->habits()->sum(fn (Habit $habit) => max(
            $habit->targetFor($week) * $elapsed,
            min($habit->doneIn($week), $habit->targetFor($week)),
        ));

        $milestone = $week->milestone_hit_at || $elapsed >= 1.0 ? RoadmapWeek::MILESTONE_POINTS : 0;

        return $tasks + $habits + $milestone;
    }

    /**
     * The projection for a GoalWeek: the share on current pace and its band. A finished week projects its real
     * result; one that hasn't started (or has nothing due yet) has no projection.
     *
     * @return array{share: float|null, percent: int|null, band: GoalWeekResult|null}
     */
    public static function project(RoadmapWeek $week, ?CarbonInterface $today = null): array
    {
        $today ??= Clock::today();

        $share = ! $week->isStarted()
            ? null
            : (self::elapsed($week, $today) >= 1.0
                ? $week->completionShare()
                : self::share($week->earnedPoints(), self::duePoints($week, $today)));

        $verified = $week->isSmashVerified();

        return [
            'share' => $share,
            'percent' => $share === null ? null : GoalWeekResult::creditedPercent($share, $verified),
            'band' => self::band($share, $verified),
        ];
    }
}
