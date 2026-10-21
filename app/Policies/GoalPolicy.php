<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

class GoalPolicy
{
    /**
     * Determine whether the user can view the goal.
     */
    public function view(User $user, Goal $goal): bool
    {
        return $goal->user()->is($user);
    }

    /**
     * Determine whether the user can update the goal.
     */
    public function update(User $user, Goal $goal): bool
    {
        return $goal->user()->is($user);
    }

    /**
     * Determine whether the user can delete the goal.
     */
    public function delete(User $user, Goal $goal): bool
    {
        return $goal->user()->is($user);
    }
}
