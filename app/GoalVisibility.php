<?php

namespace App;

/**
 * Who can see a goal on its owner's profile: its title, kind, date, progress and league. Never plan details, notes
 * or intake answers, whoever is looking.
 */
enum GoalVisibility: string
{
    case Everyone = 'everyone';
    case FriendsAndLeagues = 'friends_and_leagues';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone in GoalLine',
            self::FriendsAndLeagues => 'Friends and league mates',
            self::Private => 'Only me',
        };
    }
}
