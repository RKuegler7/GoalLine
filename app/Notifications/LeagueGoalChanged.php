<?php

namespace App\Notifications;

use App\Models\League;
use App\Models\User;

/**
 * The league owner changed the shared league goal. Members' own goals don't change; this just keeps them informed.
 */
class LeagueGoalChanged extends GoalLineNotification
{
    public function __construct(public League $league) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "The league goal for {$this->league->name} is now: {$this->league->goalSummary()}. Your own goal stays as it is.",
            'url' => route('leagues.show', $this->league),
            'actor_id' => $this->league->commissioner_id,
        ];
    }
}
