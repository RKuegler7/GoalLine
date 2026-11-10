<?php

namespace App\Services;

use App\GoalWeekResult;
use App\Models\RoadmapWeek;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The band-up moment. Wraps a check-in: notes the GoalWeek's band before and after (from the scoring code, never
 * the browser), and when the change moves the week UP a band, returns what to celebrate, at most once per band per
 * GoalWeek. Going down is quiet: nothing is returned.
 *
 * The band here is the one the percent reaches (150% and up is Smashed even before proof); needsProof says when a
 * Smashed week still needs proof or the opponent's OK to count in full, so the celebration can nudge for it.
 */
final class BandProgress
{
    /**
     * Run a check-in and return the celebration it earned, if any.
     *
     * @param  Closure(): mixed  $change
     * @return array{band: string, label: string, weekId: int, percent: int, needsProof: bool, goalUrl: string}|null
     */
    public static function around(RoadmapWeek $week, Closure $change): ?array
    {
        $before = self::reached($week->fresh(['tasks', 'goal.habits.logs']));
        $change();
        $after = $week->fresh(['tasks', 'goal.habits.logs']);

        $band = self::crossed($before, self::reached($after));

        if (! $band || ! self::claim($after, $band)) {
            return null;
        }

        return [
            'band' => $band->value,
            'label' => $band->label(),
            'weekId' => $after->id,
            'percent' => GoalWeekResult::creditedPercent($after->completionShare()),
            'needsProof' => $band === GoalWeekResult::Smashed && ! $after->isSmashVerified(),
            'goalUrl' => route('goals.show', $after->goal_id).'#goalweek-'.$after->position,
        ];
    }

    /**
     * The same for a change that touches several GoalWeeks at once ("Mark all done"): the biggest band-up wins.
     *
     * @param  iterable<RoadmapWeek>  $weeks
     * @param  Closure(): mixed  $change
     * @return array{band: string, label: string, weekId: int, percent: int, needsProof: bool, goalUrl: string}|null
     */
    public static function aroundMany(iterable $weeks, Closure $change): ?array
    {
        $before = [];

        foreach ($weeks as $week) {
            $before[$week->id] = [$week, self::reached($week->fresh(['tasks', 'goal.habits.logs']))];
        }

        $change();
        $best = null;

        foreach ($before as [$week, $was]) {
            $after = $week->fresh(['tasks', 'goal.habits.logs']);
            $band = self::crossed($was, self::reached($after));

            if ($band && (! $best || $band->points() > $best[1]->points())) {
                $best = [$after, $band];
            }
        }

        if (! $best || ! self::claim($best[0], $best[1])) {
            return null;
        }

        [$week, $band] = $best;

        return [
            'band' => $band->value,
            'label' => $band->label(),
            'weekId' => $week->id,
            'percent' => GoalWeekResult::creditedPercent($week->completionShare()),
            'needsProof' => $band === GoalWeekResult::Smashed && ! $week->isSmashVerified(),
            'goalUrl' => route('goals.show', $week->goal_id).'#goalweek-'.$week->position,
        ];
    }

    /**
     * The band a GoalWeek's percent reaches (proof aside), or null before it starts.
     */
    public static function reached(RoadmapWeek $week): ?GoalWeekResult
    {
        return $week->isStarted() ? GoalWeekResult::score($week->completionShare(), smashVerified: true) : null;
    }

    /**
     * The band reached when it's higher than before, or null for no change or a step down.
     */
    public static function crossed(?GoalWeekResult $before, ?GoalWeekResult $after): ?GoalWeekResult
    {
        if ($after === null) {
            return null;
        }

        return $after->points() > ($before?->points() ?? GoalWeekResult::Short->points()) ? $after : null;
    }

    /**
     * Once per band per GoalWeek: true the first time, false after (until a few days after the week ends).
     */
    public static function claim(RoadmapWeek $week, GoalWeekResult $band): bool
    {
        return Cache::add("band-up:{$week->id}:{$band->value}", true, $week->ends_on->copy()->endOfDay()->addDays(3));
    }
}
