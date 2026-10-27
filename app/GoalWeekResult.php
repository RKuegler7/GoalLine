<?php

namespace App;

/**
 * A GoalWeek's band, set by the share of its planned points that were earned:
 * Short of the line under 90%, In line 90–100%, Past the line over 100%, Smashed the line at 150% and up
 * (uncapped). A Smashed week only counts as Smashed with proof or the matchup opponent's OK; until then
 * it's credited as Past the line.
 */
enum GoalWeekResult: string
{
    case Short = 'short';
    case InLine = 'in_line';
    case Past = 'past';
    case Smashed = 'smashed';

    /**
     * At or above this share of planned points, a GoalWeek is in line.
     */
    public const IN_LINE_AT = 0.90;

    /**
     * Above this share, a GoalWeek is past the line.
     */
    public const PAST_ABOVE = 1.00;

    /**
     * At or above this share, a GoalWeek smashed the line (with proof or the opponent's OK).
     */
    public const SMASHED_AT = 1.50;

    /**
     * Below this share, an In line result is a close call.
     */
    public const SAFELY_IN_LINE_AT = 0.95;

    /**
     * Score a GoalWeek from the share of planned points earned (1.0 is exactly the plan).
     * A share that would be Smashed counts only when the week is verified; otherwise it's Past.
     */
    public static function score(float $share, bool $smashVerified = true): self
    {
        // Rounded to whole hundredths of a percent, so 0.9 doesn't become 0.8999… and fall short.
        $share = round($share, 4);

        return match (true) {
            $share < self::IN_LINE_AT => self::Short,
            $share <= self::PAST_ABOVE => self::InLine,
            $share >= self::SMASHED_AT && $smashVerified => self::Smashed,
            default => self::Past,
        };
    }

    /**
     * Whether a share is high enough to smash the line, verified or not.
     */
    public static function reachesSmashed(float $share): bool
    {
        return round($share, 4) >= self::SMASHED_AT;
    }

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Short => 'Short of the line',
            self::InLine => 'In line',
            self::Past => 'Past the line',
            self::Smashed => 'Smashed the line',
        };
    }

    /**
     * Line points for the band: 0 to 3.
     */
    public function points(): int
    {
        return match ($this) {
            self::Short => 0,
            self::InLine => 1,
            self::Past => 2,
            self::Smashed => 3,
        };
    }
}
