<?php

namespace App\Notifications;

use App\Models\User;

class NewFollower extends GoalLineNotification
{
    public function __construct(public User $follower) {}

    public function category(): string
    {
        return 'social';
    }

    protected function message(User $notifiable): array
    {
        $followsBack = $notifiable->following()->whereKey($this->follower->id)->exists();

        return [
            'text' => $this->follower->name.($followsBack ? ' followed you back. You\'re friends now.' : ' started following you.'),
            'url' => route('profiles.show', $this->follower),
            'actor_id' => $this->follower->id,
        ];
    }
}
