<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;

class ChallengePolicy
{
    /**
     * Members see the challenge and its leaderboard. Everyone else joins with the invite link first.
     */
    public function view(User $user, Challenge $challenge): bool
    {
        return $challenge->members()->where('user_id', $user->id)->exists();
    }

    /**
     * The creator can call it off before it starts.
     */
    public function delete(User $user, Challenge $challenge): bool
    {
        return $challenge->creator_id === $user->id && ! $challenge->hasStarted();
    }
}
