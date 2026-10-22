<?php

namespace App\Notifications;

use App\Models\League;
use App\Models\User;

/**
 * A GoalLeague week is final, or the whole season is: the recap is in.
 */
class LeagueRecap extends GoalLineNotification
{
    public function __construct(public League $league, public string $text) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return ['text' => $this->text, 'url' => route('leagues.recap', $this->league)];
    }
}
