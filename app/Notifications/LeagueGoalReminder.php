<?php

namespace App\Notifications;

use App\Models\League;
use App\Models\User;

/**
 * The league owner's gentle nudge to finish setting a goal. In the app always; by email only when the notification
 * emails are switched on (App\Outbound).
 */
class LeagueGoalReminder extends GoalLineNotification
{
    public function __construct(public League $league) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "Your spot in {$this->league->name} is waiting. Set your goal and you're ready for the next matchup.",
            'url' => route('leagues.my-goal', $this->league),
            'actor_id' => $this->league->commissioner_id,
        ];
    }
}
