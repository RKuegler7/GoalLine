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
            self::Leagues => 'My league mates',
            self::Everyone => 'Everyone on GoalLine',
        };
    }

    /**
     * What the choice means.
     */
    public function description(): string
    {
        return match ($this) {
            self::Private => 'Your profile is hidden from everyone else.',
            self::Leagues => 'People in your leagues can see your profile.',
            self::Everyone => 'Anyone signed in to GoalLine can see your profile.',
        };
    }
}
