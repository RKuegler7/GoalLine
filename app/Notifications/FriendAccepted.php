<?php

namespace App\Notifications;

use App\Models\User;

class FriendAccepted extends GoalLineNotification
{
    public function __construct(public User $friend) {}

    public function category(): string
    {
        return 'social';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->friend->name} accepted your friend request. You're friends now.",
            'url' => route('profiles.show', $this->friend),
            'actor_id' => $this->friend->id,
        ];
    }
}
