<?php

namespace App\Http\Controllers;

use App\LeagueStatus;
use App\Models\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatchupHubController extends Controller
{
    /**
     * The Matchups tab. In season: straight to this week's matchups (bye weeks and one-member leagues have their own
     * pages there). Otherwise a real page, never a bounce to another tab: the season that's about to kick off, the
     * one that just ended, or no GoalLeague yet, each with a clear next step and the member's week against the line.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $leagues = $user->leagues()->with('members')->orderByDesc('starts_on')->get();

        if ($active = $leagues->first(fn (League $league) => $league->status() === LeagueStatus::Active)) {
            return redirect()->route('leagues.matchups', $active);
        }

        $forming = $leagues->filter(fn (League $league) => $league->status() === LeagueStatus::Forming)->sortBy('starts_on')->first();
        $finished = $leagues->first(fn (League $league) => $league->status() === LeagueStatus::Finished);

        return view('matchups.hub', [
            'state' => match (true) {
                $forming !== null => 'kickoff',
                $finished !== null => 'season-over',
                default => 'no-league',
            },
            'forming' => $forming,
            'finished' => $finished,
            'goals' => $user->goals()->inPlay()->with(['weeks.tasks', 'habits.logs'])->orderBy('target_date')->get(),
        ]);
    }
}
