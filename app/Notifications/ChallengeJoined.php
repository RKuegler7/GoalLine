<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\User;

class ChallengeJoined extends GoalLineNotification
{
    public function __construct(public Challenge $challenge, public User $member) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->member->name} joined your challenge “{$this->challenge->title}”.",
            'url' => route('challenges.show', $this->challenge),
            'actor_id' => $this->member->id,
        ];
    }
}
