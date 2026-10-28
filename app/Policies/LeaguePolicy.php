<?php

namespace App\Policies;

use App\LeagueStatus;
use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    /**
     * Members can see their league.
     */
    public function view(User $user, League $league): bool
    {
        return $league->memberFor($user) !== null;
    }

    /**
     * Members can leave before kickoff; the commissioner stays to run it.
     */
    public function leave(User $user, League $league): bool
    {
        return $this->view($user, $league)
            && $league->commissioner_id !== $user->id
            && $league->status() === LeagueStatus::Forming;
    }

    /**
     * Only the league owner manages invites and members.
     */
    public function manage(User $user, League $league): bool
    {
        return $league->commissioner_id === $user->id;
    }

    /**
     * The commissioner can call off a league before kickoff.
     */
    public function delete(User $user, League $league): bool
    {
        return $league->commissioner_id === $user->id && $league->status() === LeagueStatus::Forming;
    }
}
