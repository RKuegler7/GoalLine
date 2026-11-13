<?php

namespace App;

/**
 * Who sees a photo added to a checked-off task.
 */
enum PostVisibility: string
{
    case Proof = 'proof';
    case League = 'league';
    case LeagueFriends = 'league_friends';
    // Anyone on GoalLine, in Explore. Never the default and never pre-selected: always a deliberate choice.
    case Public = 'public';

    /**
     * The label shown to people. Without a GoalLeague, "League + friends" is just friends.
     */
    public function label(bool $inLeague = true): string
    {
        return match ($this) {
            self::Proof => 'Proof only',
            self::League => 'League',
            self::LeagueFriends => $inLeague ? 'League + friends' : 'Friends',
            self::Public => 'Public (Explore)',
        };
    }

    /**
     * What the choice means.
     */
    public function description(bool $inLeague = true): string
    {
        return match ($this) {
            self::Proof => 'Just the Verified check. Only you, your opponent, and the league owner can open it.',
            self::League => 'Posted to your GoalLeague feed.',
            self::LeagueFriends => $inLeague ? 'Posted to your GoalLeague and to your friends.' : 'Posted to your friends.',
            self::Public => 'Anyone on GoalLine can see this post.',
        };
    }
}
