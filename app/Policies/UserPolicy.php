<?php

namespace App\Policies;

use App\Models\User;
use App\ProfileVisibility;

class UserPolicy
{
    /**
     * Whether the viewer can see this person's profile, based on the person's visibility setting.
     */
    public function viewProfile(User $viewer, User $user): bool
    {
        return match (true) {
            $viewer->is($user) => true,
            $user->profile_visibility === ProfileVisibility::Everyone => true,
            $user->profile_visibility === ProfileVisibility::Leagues => $viewer->isFriendsWith($user) || $viewer->sharesLeagueWith($user),
            default => false,
        };
    }
}
