<?php

namespace App;

/**
 * Who can see someone's friends list. Friends are mutual (both agree), so there's one list, not Followers and
 * Following: those would be identical.
 */
enum FriendsListVisibility: string
{
    case FriendsAndLeagues = 'friends_and_leagues';
    case Friends = 'friends';
    case OnlyMe = 'only_me';

    public function label(): string
    {
        return match ($this) {
            self::FriendsAndLeagues => 'Friends and league mates',
            self::Friends => 'Friends only',
            self::OnlyMe => 'Only me',
        };
    }
}
