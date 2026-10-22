<?php

namespace App\Services;

use App\Badge;
use App\Models\Challenge;
use App\Notifications\ChallengeFinished;

/**
 * Once a challenge's last check-in window closes: tell everyone where they finished, and give finishers the badge.
 */
class ChallengeWrapUp
{
    public function __construct(private BadgeAwarder $awarder) {}

    /**
     * Wrap up every challenge that's ready. Returns how many were wrapped up.
     */
    public function run(): int
    {
        $ready = Challenge::query()
            ->whereNull('finalized_at')
            ->whereDate('ends_on', '<', today()->subDays(Challenge::CHECK_IN_GRACE_DAYS))
            ->with('members.user', 'checkIns')
            ->get();

        foreach ($ready as $challenge) {
            $this->wrapUp($challenge);
        }

        return $ready->count();
    }

    private function wrapUp(Challenge $challenge): void
    {
        $standings = $challenge->standings();
        $field = $standings->count();

        foreach ($standings as $row) {
            $user = $row['member']->user;
            $place = $field > 1 ? " You finished #{$row['rank']} of {$field}" : ' You finished';
            $text = "\u{201C}{$challenge->title}\u{201D} is over.{$place} with {$row['days']} of {$challenge->target_days} days"
                .($row['finished'] ? '. Challenge complete!' : '.');

            $user->notify(new ChallengeFinished($challenge, $text));

            if ($row['finished']) {
                $this->awarder->award($user, Badge::ChallengeFinisher);
            }
        }

        $challenge->forceFill(['finalized_at' => now()])->save();
    }
}
