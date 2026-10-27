<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who can see and vouch for a member's check-ins: their GoalLeague mates, the league owner (commissioner),
 * and, for a given GoalWeek, their matchup opponent.
 */
class FairPlay
{
    /**
     * GoalLeagues the GoalWeek's goal plays in, during that GoalWeek.
     *
     * @return Collection<int, League>
     */
    public function leaguesFor(RoadmapWeek $week): Collection
    {
        return LeagueMember::query()
            ->where('goal_id', $week->goal_id)
            ->with('league.members.user', 'league.commissioner')
            ->get()
            ->pluck('league')
            ->filter(fn (League $league) => $league->weekNumberOn($week->ends_on) !== null)
            ->values();
    }

    /**
     * The member's matchup opponents for the GoalWeek, one per league (a bye vs. the league median has none).
     *
     * @return Collection<int, User>
     */
    public function opponentsOf(RoadmapWeek $week): Collection
    {
        return $this->leaguesFor($week)
            ->map(function (League $league) use ($week) {
                $number = $league->weekNumberOn($week->ends_on);

                foreach ((new LeagueTable($league))->pairings($number) as [$home, $away]) {
                    if ($home->goal_id === $week->goal_id) {
                        return $away?->user;
                    }

                    if ($away?->goal_id === $week->goal_id) {
                        return $home->user;
                    }
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * Proof is always visible to the member, their matchup opponent, and the league owner.
     */
    public function canSeeProof(User $viewer, RoadmapTask $task): bool
    {
        $week = $task->week;

        return $week->goal->user_id === $viewer->id
            || $this->leaguesFor($week)->contains(fn (League $league) => $league->commissioner_id === $viewer->id)
            || $this->opponentsOf($week)->contains(fn (User $opponent) => $opponent->is($viewer));
    }

    /**
     * Any league mate can ask to see the work behind someone else's check-in.
     */
    public function canRequestProof(User $viewer, RoadmapTask $task): bool
    {
        $week = $task->week;

        return $week->goal->user_id !== $viewer->id
            && $task->completed_at !== null
            && $this->leaguesFor($week)->contains(fn (League $league) => $league->members->contains('user_id', $viewer->id));
    }

    /**
     * Only the matchup opponent can confirm a Smashed week without proof.
     */
    public function canConfirmSmashed(User $viewer, RoadmapWeek $week): bool
    {
        return $this->opponentsOf($week)->contains(fn (User $opponent) => $opponent->is($viewer));
    }
}
