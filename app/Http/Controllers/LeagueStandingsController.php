<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueMember;
use App\Services\LeagueStats;
use App\Services\LeagueTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueStandingsController extends Controller
{
    /**
     * The full standings: league highlights, the table with each member's form, a sort control (the official order
     * stays the default) and the viewer's season so far. Members only.
     */
    public function index(Request $request, League $league): View
    {
        $stats = $this->stats($league);
        $sort = array_key_exists((string) $request->query('sort'), LeagueStats::SORTS) ? $request->query('sort') : 'points';
        $me = $league->memberFor($request->user());

        return view('leagues.standings', [
            'league' => $league,
            'stats' => $stats,
            'rows' => $stats->standings($sort),
            'sort' => $sort,
            'summary' => $stats->summary(),
            'series' => $stats->series($me),
            'me' => $me,
        ]);
    }

    /**
     * One member's season in this league: record and its close calls, points by kind, average percent of plan, best
     * week, weeks on the line, bands, plan status and every week's result. Only league members can open it, and it
     * shows nothing private: no notes, reflections, intake answers or email.
     */
    public function show(Request $request, League $league, LeagueMember $member): View
    {
        $stats = $this->stats($league);
        $row = $stats->standings()->first(fn (array $row) => $row['member']->is($member));

        return view('leagues.standings-player', [
            'league' => $league,
            'member' => $row['member'],
            'row' => $row,
            'player' => $row['stats'],
        ]);
    }

    private function stats(League $league): LeagueStats
    {
        $league->load('members.user', 'members.goal.weeks.tasks', 'members.goal.habits.logs', 'results');

        return new LeagueStats(new LeagueTable($league));
    }
}
