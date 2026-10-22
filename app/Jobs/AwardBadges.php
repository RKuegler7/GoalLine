<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BadgeAwarder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Check whether a member has earned any new personal badges. Runs after check-ins, cheers,
 * finished goals, and league joins, and once a day for results that just became final.
 */
class AwardBadges implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(BadgeAwarder $awarder): void
    {
        $awarder->checkPersonal($this->user);
    }
}
