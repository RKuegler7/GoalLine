<?php

namespace App\Notifications;

use App\Models\HighlightComment;
use App\Models\User;
use Illuminate\Support\Str;

class HighlightCommented extends GoalLineNotification
{
    public function __construct(public HighlightComment $comment) {}

    public function category(): string
    {
        return 'mentions';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => $this->comment->user->name.' commented on your highlight: “'.Str::limit($this->comment->body, 120).'”',
            'url' => route('highlights.index').'#highlight-'.$this->comment->highlight_id,
            'actor_id' => $this->comment->user_id,
        ];
    }
}
