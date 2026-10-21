<?php

namespace App;

enum MatchupOutcome: string
{
    case Win = 'win';
    case Tie = 'tie';
    case Loss = 'loss';

    /**
     * Compare two GoalWeek result point totals, soccer-style.
     */
    public static function compare(float $mine, float $theirs): self
    {
        return match (true) {
            $mine > $theirs => self::Win,
            $mine < $theirs => self::Loss,
            default => self::Tie,
        };
    }

    /**
     * Matchup points: 3 for a win, 1 for a tie, 0 for a loss.
     */
    public function points(): int
    {
        return match ($this) {
            self::Win => 3,
            self::Tie => 1,
            self::Loss => 0,
        };
    }

    /**
     * One-letter form, as in a W–T–L record.
     */
    public function letter(): string
    {
        return match ($this) {
            self::Win => 'W',
            self::Tie => 'T',
            self::Loss => 'L',
        };
    }
}
