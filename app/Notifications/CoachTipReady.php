<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WeekReview;

class CoachTipReady extends GoalLineNotification
{
    public function __construct(public WeekReview $review) {}

    public function category(): string
    {
        return 'results';
    }

    protected function message(User $notifiable): array
    {
        $week = $this->review->week;

        return [
            'text' => "Your coach's tip for after {$week->label()} of “{$week->goal->title}” is in.",
            'url' => route('goalweeks.review', $week),
        ];
    }
}
