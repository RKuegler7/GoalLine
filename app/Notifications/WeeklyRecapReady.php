<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Sunday evening: the member's week in GoalLine, and a nudge to plan the next one.
 */
class WeeklyRecapReady extends GoalLineNotification
{
    public function __construct(public string $headline) {}

    public function category(): string
    {
        return 'recap';
    }

    protected function message(User $notifiable): array
    {
        return ['text' => $this->headline, 'url' => route('recap.week')];
    }
}
