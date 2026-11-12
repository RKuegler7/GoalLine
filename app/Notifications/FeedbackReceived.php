<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * A member sent feedback or reported a problem from Help. In the app owner's Inbox; the full note is on the admin
 * page. The sender's email is never shown to anyone else.
 */
class FeedbackReceived extends GoalLineNotification
{
    public function __construct(public User $sender, public string $body, public ?string $page) {}

    public function category(): string
    {
        return 'news';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => 'Feedback from '.$this->sender->name.': '.Str::limit($this->body, 140).($this->page ? ' (on '.Str::limit($this->page, 80).')' : ''),
            'url' => route('admin.index'),
            'actor_id' => $this->sender->id,
        ];
    }
}
