<?php

namespace App\Notifications;

use App\Models\User;

/**
 * News from the GoalLine team.
 */
class Announcement extends GoalLineNotification
{
    public function __construct(public string $text, public ?string $url = null) {}

    public function category(): string
    {
        return 'news';
    }

    protected function message(User $notifiable): array
    {
        return ['text' => $this->text, 'url' => $this->url ?? route('dashboard')];
    }
}
