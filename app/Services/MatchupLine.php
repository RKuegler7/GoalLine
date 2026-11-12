<?php

namespace App\Services;

use App\GoalWeekResult;

/**
 * "The line" on the matchup screen: a field track from 0% to past Smashed, with yard lines at the band thresholds
 * and each player's puck at their credited percent of plan. Positions and words come from the scoring code's own
 * thresholds, so the drawing can never disagree with a score. The view only places what this returns.
 */
class MatchupLine
{
    /**
     * Room past the Smashed line, in percent of plan, so a Smashed puck still sits on the track.
     */
    public const HEADROOM = 25;

    /**
     * The track's right end, in percent of plan (175 with the current bands).
     */
    public static function max(): int
    {
        return (int) round(GoalWeekResult::SMASHED_AT * 100) + self::HEADROOM;
    }

    /**
     * Where a percent of plan sits along the track, from 0 to 100 (% of the track's width). Anything past the end
     * sits at the end; nothing goes below 0.
     */
    public static function position(?int $percent): float
    {
        return round(min(max($percent ?? 0, 0), self::max()) / self::max() * 100, 2);
    }

    /**
     * Whether the percent is past the right end of the track (shown as "150%+" territory at the end).
     */
    public static function isOffTheEnd(?int $percent): bool
    {
        return ($percent ?? 0) > self::max();
    }

    /**
     * The yard lines: the band thresholds and the plan line, left to right.
     *
     * @return list<array{percent: int, position: float, label: string, key: string}>
     */
    public static function markers(): array
    {
        return array_map(fn (array $marker) => $marker + ['position' => self::position($marker['percent'])], [
            ['percent' => (int) round(GoalWeekResult::IN_LINE_AT * 100), 'label' => 'In line', 'key' => 'in_line'],
            ['percent' => (int) round(GoalWeekResult::PAST_ABOVE * 100), 'label' => 'Plan', 'key' => 'plan'],
            ['percent' => (int) round(GoalWeekResult::SMASHED_AT * 100), 'label' => 'Smashed', 'key' => 'smashed'],
        ]);
    }

    /**
     * The coloured zones under the track, one per band.
     *
     * @return list<array{band: GoalWeekResult, from: float, to: float}>
     */
    public static function zones(): array
    {
        $inLine = self::position((int) round(GoalWeekResult::IN_LINE_AT * 100));
        $past = self::position((int) round(GoalWeekResult::PAST_ABOVE * 100));
        $smashed = self::position((int) round(GoalWeekResult::SMASHED_AT * 100));

        return [
            ['band' => GoalWeekResult::Short, 'from' => 0.0, 'to' => $inLine],
            ['band' => GoalWeekResult::InLine, 'from' => $inLine, 'to' => $past],
            ['band' => GoalWeekResult::Past, 'from' => $past, 'to' => $smashed],
            ['band' => GoalWeekResult::Smashed, 'from' => $smashed, 'to' => 100.0],
        ];
    }

    /**
     * The text alternative for one player: "Ryan is at 82%, Short of the line; needs 7 more points to reach In line."
     *
     * @param  array{percent: int|null, result: GoalWeekResult|null, next: array{band: GoalWeekResult, points: int}|null}  $side
     */
    public static function describe(string $name, array $side): string
    {
        if ($side['percent'] === null) {
            return "{$name} has no GoalWeek this week.";
        }

        $sentence = "{$name} is at {$side['percent']}%, ".($side['result']?->label() ?? GoalWeekResult::Short->label());

        return $sentence.($side['next']
            ? "; needs {$side['next']['points']} more ".str('point')->plural($side['next']['points'])." to reach {$side['next']['band']->label()}."
            : '.');
    }
}
