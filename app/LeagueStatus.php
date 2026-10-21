<?php

namespace App;

enum LeagueStatus: string
{
    case Forming = 'forming';
    case Active = 'active';
    case Finished = 'finished';

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Forming => 'Forming',
            self::Active => 'In season',
            self::Finished => 'Season over',
        };
    }
}
