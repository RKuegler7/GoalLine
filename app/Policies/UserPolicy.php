<?php

namespace App\Policies;

use App\FriendsListVisibility;
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
            ! $viewer->sharesWorldWith($user) => false,
            $user->profile_visibility === ProfileVisibility::Everyone => true,
            $user->profile_visibility === ProfileVisibility::Leagues => $viewer->isFriendsWith($user) || $viewer->sharesLeagueWith($user),
            default => false,
        };
    }

    /**
     * Whether the viewer can see this person's friends list (their "Who can see my friends list" setting). Someone who
     * can't still sees how many mutual friends they share.
     */
    public function viewFriends(User $viewer, User $user): bool
    {
        return match (true) {
            $viewer->is($user) => true,
            ! $this->viewProfile($viewer, $user) => false,
            $user->friends_list_visibility === FriendsListVisibility::FriendsAndLeagues => $viewer->isFriendsWith($user) || $viewer->sharesLeagueWith($user),
            $user->friends_list_visibility === FriendsListVisibility::Friends => $viewer->isFriendsWith($user),
            default => false,
        };
    }
}
