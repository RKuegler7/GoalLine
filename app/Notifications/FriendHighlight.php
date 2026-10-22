<?php

namespace App\Notifications;

use App\Models\Highlight;
use App\Models\User;

/**
 * Someone you follow shared a win. Inbox only: these can be frequent.
 */
class FriendHighlight extends GoalLineNotification
{
    public function __construct(public Highlight $highlight) {}

    public function category(): string
    {
        return 'social';
    }

    public function via(User $notifiable): array
    {
        return ['database'];
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => $this->highlight->user->name.' shared a highlight: '.$this->highlight->headline,
            'url' => route('highlights.index').'#highlight-'.$this->highlight->id,
            'actor_id' => $this->highlight->user_id,
        ];
    }
}
