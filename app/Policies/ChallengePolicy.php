<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;

class ChallengePolicy
{
    /**
     * Members see the Challenge, its Standings and each other's days. Everyone else sees the join page first.
     */
    public function view(User $user, Challenge $challenge): bool
    {
        return $challenge->members()->where('user_id', $user->id)->exists();
    }

    /**
     * The owner (who started it) edits it, removes members and decides disputes.
     */
    public function manage(User $user, Challenge $challenge): bool
    {
        return $challenge->creator_id === $user->id;
    }

    /**
     * The owner can call it off before it starts.
     */
    public function delete(User $user, Challenge $challenge): bool
    {
        return $challenge->creator_id === $user->id && ! $challenge->hasStarted();
    }
}
