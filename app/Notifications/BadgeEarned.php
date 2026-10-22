<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserBadge;

class BadgeEarned extends GoalLineNotification
{
    public function __construct(public UserBadge $earned) {}

    public function category(): string
    {
        return 'badges';
    }

    protected function message(User $notifiable): array
    {
        $badge = $this->earned->badge;

        if ($this->earned->league) {
            return [
                'text' => "You won {$badge->label()} in {$this->earned->league->name}! {$this->earned->detail}",
                'url' => route('leagues.recap', $this->earned->league),
                'badge' => $badge->value,
            ];
        }

        return [
            'text' => "Badge earned: {$badge->label()}. {$badge->description()}",
            'url' => route('profile.show').'#badges',
            'badge' => $badge->value,
        ];
    }
}
