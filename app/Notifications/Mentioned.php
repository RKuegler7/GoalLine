<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Someone @mentioned you in a GoalLeague chat or a highlight comment.
 */
class Mentioned extends GoalLineNotification
{
    public function __construct(
        public User $author,
        public string $where,
        public string $body,
        public string $url,
    ) {}

    public function category(): string
    {
        return 'mentions';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->author->name} mentioned you {$this->where}: “".Str::limit($this->body, 120).'”',
            'url' => $this->url,
            'actor_id' => $this->author->id,
        ];
    }
}
