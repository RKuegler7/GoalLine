<?php

namespace App\Services;

use App\MatchupOutcome;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

/**
 * One member's at-a-glance view of a GoalLeague: rank, points, this week's matchup, and unread chat.
 */
class LeagueSnapshot
{
    public LeagueTable $table;

    public LeagueMember $me;

    public function __construct(public League $league, User $user)
    {
        $this->table = new LeagueTable($league);
        $this->table->settle();
        $this->me = $league->memberFor($user);
    }

    /**
     * The member's place in the standings, starting at 1.
     */
    public function rank(): int
    {
        return $this->table->standings()->search(fn (array $row) => $row['member']->is($this->me)) + 1;
    }

    /**
     * The member's total points this season.
     */
    public function points(): int
    {
        return $this->table->standings()->first(fn (array $row) => $row['member']->is($this->me))['total'] ?? 0;
    }

    /**
     * This week's matchup from the member's side: their result, the opponent (or the league median), and who leads.
     *
     * @return array{mine: array, opponent: LeagueMember|null, theirs: array|null, median: float|null, outcome: MatchupOutcome}|null
     */
    public function matchup(): ?array
    {
        $week = $this->league->currentWeek();

        if ($week === null) {
            return null;
        }

        foreach ($this->table->matchups($week) as $matchup) {
            if ($matchup['home']->is($this->me)) {
                return [
                    'mine' => $matchup['homeResult'],
                    'opponent' => $matchup['away'],
                    'theirs' => $matchup['awayResult'],
                    'median' => $matchup['median'],
                    'outcome' => $matchup['homeOutcome'],
                ];
            }

            if ($matchup['away']?->is($this->me)) {
                return [
                    'mine' => $matchup['awayResult'],
                    'opponent' => $matchup['home'],
                    'theirs' => $matchup['homeResult'],
                    'median' => null,
                    'outcome' => $matchup['awayOutcome'],
                ];
            }
        }

        return null;
    }

    /**
     * Chat messages from others the member hasn't seen.
     */
    public function unread(): int
    {
        return $this->me->unreadCount();
    }
}
