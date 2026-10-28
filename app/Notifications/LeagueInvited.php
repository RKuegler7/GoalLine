<?php

namespace App\Notifications;

use App\Models\LeagueInvite;
use App\Models\User;

class LeagueInvited extends GoalLineNotification
{
    public function __construct(public LeagueInvite $invite, public User $inviter) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->inviter->name} invited you to the GoalLeague {$this->invite->league->name}.",
            'url' => $this->invite->url(),
            'actor_id' => $this->inviter->id,
        ];
    }
}
