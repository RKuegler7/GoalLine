<?php

namespace App\Notifications;

use App\Models\League;
use App\Models\User;

class LeagueMemberJoined extends GoalLineNotification
{
    public function __construct(public League $league, public User $member) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => $this->member->name.' joined '.$this->league->name.'.',
            'url' => route('leagues.show', $this->league),
            'actor_id' => $this->member->id,
        ];
    }
}
