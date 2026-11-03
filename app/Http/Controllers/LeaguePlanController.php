<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueMember;
use Illuminate\View\View;

class LeaguePlanController extends Controller
{
    /**
     * A league mate's plan, for the league: each GoalWeek's planned workload against its minimum, lighter weeks and
     * their reasons, and the plan's change history. Never their private notes, intake answers, or reflections.
     */
    public function show(League $league, LeagueMember $member): View
    {
        abort_unless($member->league_id === $league->id, 404);
        $goal = $member->goal?->load('weeks.tasks', 'habits.logs');

        return view('leagues.plan', [
            'league' => $league,
            'member' => $member->load('user'),
            'goal' => $goal,
            'versions' => $goal?->planVersions()->latest('id')->limit(15)->get() ?? collect(),
        ]);
    }
}
