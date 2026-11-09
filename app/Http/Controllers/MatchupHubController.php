<?php

namespace App\Http\Controllers;

use App\LeagueStatus;
use App\Models\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchupHubController extends Controller
{
    /**
     * The Matchups tab: straight to this week's matchups in the member's in-season GoalLeague (the one with the most
     * recent activity first), or to GoalLeagues when they don't have one yet.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $leagues = $request->user()->leagues()->with('members')->get();
        $league = $leagues->first(fn (League $league) => $league->status() === LeagueStatus::Active)
            ?? $leagues->first(fn (League $league) => $league->status() === LeagueStatus::Finished);

        if (! $league) {
            return redirect()->route('leagues.index')
                ->with('status', 'Matchups start when your GoalLeague kicks off. Start one or join with a code.');
        }

        return redirect()->route('leagues.matchups', $league);
    }
}
