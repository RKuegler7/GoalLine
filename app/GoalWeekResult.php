<?php

namespace App;

enum GoalWeekResult: string
{
    case Short = 'short';
    case InLine = 'in_line';
    case Past = 'past';
    case Smashed = 'smashed';

    /**
     * Below this share of planned tasks, a GoalWeek falls short of the line.
     */
    public const IN_LINE_AT = 0.70;

    /**
     * Below this share, an In line result is a close call.
     */
    public const SAFELY_IN_LINE_AT = 0.80;

    /**
     * Bonus work needed to smash the line, as a share of planned tasks (at least one task).
     */
    public const SMASH_BONUS_SHARE = 0.20;

    /**
     * Score a GoalWeek from its planned tasks, bonus tasks, and milestone.
     */
    public static function score(int $planned, int $plannedDone, int $bonusDone, bool $milestoneHit): self
    {
        $share = $planned > 0 ? $plannedDone / $planned : 1.0;

        return match (true) {
            $share < self::IN_LINE_AT => self::Short,
            $share < 1.0 || ! $milestoneHit => self::InLine,
            $bonusDone >= max(1, (int) ceil($planned * self::SMASH_BONUS_SHARE)) => self::Smashed,
            default => self::Past,
        };
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
     * GoalWeek result points.
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
