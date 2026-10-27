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

    /**
     * The label shown to people. Without a GoalLeague, "League + friends" is just friends.
     */
    public function label(bool $inLeague = true): string
    {
        return match ($this) {
            self::Proof => 'Proof only',
            self::League => 'League',
            self::LeagueFriends => $inLeague ? 'League + friends' : 'Friends',
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
        };
    }
}
