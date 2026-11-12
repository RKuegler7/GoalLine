<?php

namespace App;

/**
 * What someone says happened on Goal Day. Self-reported: it never changes stars, standings or medals for the table.
 */
enum GoalOutcome: string
{
    case Achieved = 'achieved';
    case Partly = 'partly';
    case Short = 'short';

    /**
     * The answer as it reads on the check-in.
     */
    public function choice(): string
    {
        return match ($this) {
            self::Achieved => 'I did it',
            self::Partly => 'Partly',
            self::Short => 'I came up short',
        };
    }

    /**
     * How the result reads on cards and in league news.
     */
    public function label(): string
    {
        return match ($this) {
            self::Achieved => 'Goal achieved',
            self::Partly => 'Partly there',
            self::Short => 'Came up short',
        };
    }
}
