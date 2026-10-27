<?php

namespace App;

enum MatchupOutcome: string
{
    case Win = 'win';
    case Tie = 'tie';
    case Loss = 'loss';

    /**
     * Compare two GoalWeeks' line points: the better band wins, the same band draws.
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
     * Match points: 2 for a win, 1 for a draw, 0 for a loss. With line points (0–3), a week is worth 0–5.
     */
    public function points(): int
    {
        return match ($this) {
            self::Win => 2,
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
