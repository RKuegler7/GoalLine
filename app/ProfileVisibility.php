<?php

namespace App;

enum ProfileVisibility: string
{
    case Private = 'private';
    case Leagues = 'leagues';
    case Everyone = 'everyone';

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Private => 'Only me',
            self::Leagues => 'Friends and league mates',
            self::Everyone => 'Everyone on GoalLine',
        };
    }

    /**
     * What the choice means.
     */
    public function description(): string
    {
        return match ($this) {
            self::Private => 'Others see only your photo, name, @username, bio and counts, so they can find and add you. Your goals, posts and stats stay hidden.',
            self::Leagues => 'Your friends and the people in your GoalLeagues see your whole profile. Everyone else sees only your photo, name, @username, bio and counts.',
            self::Everyone => 'Anyone signed in to GoalLine can see your profile.',
        };
    }
}
