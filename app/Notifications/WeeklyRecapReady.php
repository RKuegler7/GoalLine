<?php

namespace App\Notifications;

use App\Models\User;
use App\Outbound;

/**
 * Sunday evening: the member's week in GoalLine, and a nudge to plan the next one.
 */
class WeeklyRecapReady extends GoalLineNotification
{
    public function __construct(public string $headline) {}

    /**
     * Paused with the GOALLINE_EMAIL_RECAPS switch.
     */
    public function emailStream(): string
    {
        return Outbound::RECAPS;
    }

    public function category(): string
    {
        return 'recap';
    }

    protected function message(User $notifiable): array
    {
        return ['text' => $this->headline, 'url' => route('recap.week')];
    }
}
