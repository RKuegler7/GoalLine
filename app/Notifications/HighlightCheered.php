<?php

namespace App\Notifications;

use App\Models\Highlight;
use App\Models\User;

class HighlightCheered extends GoalLineNotification
{
    public function __construct(public Highlight $highlight, public User $fan) {}

    public function category(): string
    {
        return 'social';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => $this->fan->name.' cheered your highlight: '.$this->highlight->headline,
            'url' => route('highlights.index').'#highlight-'.$this->highlight->id,
            'actor_id' => $this->fan->id,
            'highlight_id' => $this->highlight->id,
        ];
    }
}
