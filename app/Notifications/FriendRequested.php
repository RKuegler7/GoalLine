<?php

namespace App\Notifications;

use App\Models\User;

class FriendRequested extends GoalLineNotification
{
    public function __construct(public User $requester) {}

    public function category(): string
    {
        return 'social';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->requester->name} sent you a friend request. Friends give each other kudos and see each other's shared posts.",
            'url' => route('friends.index'),
            'actor_id' => $this->requester->id,
        ];
    }
}
