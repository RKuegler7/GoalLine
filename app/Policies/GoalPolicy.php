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
     * Determine whether the user can change the goal or its roadmap. Retired goals are read-only.
     */
    public function update(User $user, Goal $goal): bool
    {
        return $goal->user()->is($user) && $goal->isActive();
    }

    /**
     * Determine whether the user can remove the goal outright: only during its trial.
     */
    public function delete(User $user, Goal $goal): bool
    {
        return $this->update($user, $goal) && $goal->inTrial();
    }

    /**
     * Determine whether the user can retire the goal: once locked in.
     */
    public function retire(User $user, Goal $goal): bool
    {
        return $this->update($user, $goal) && ! $goal->inTrial();
    }
}
