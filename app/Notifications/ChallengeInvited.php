<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\User;

class ChallengeInvited extends GoalLineNotification
{
    public function __construct(public Challenge $challenge, public User $inviter) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->inviter->name} invited you to the Challenge {$this->challenge->title}.",
            'url' => route('challenges.join', $this->challenge->invite_code),
            'actor_id' => $this->inviter->id,
        ];
    }
}
