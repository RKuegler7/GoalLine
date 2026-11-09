<?php

namespace App\Http\Controllers;

use App\LeagueStatus;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Services\LeagueTable;
use App\Services\MatchupPager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueMatchupController extends Controller
{
    /**
     * "GoalWeek X Matchup": a pager with one page per matchup in a league week, the viewer's first, scored live
     * until the week is final. Defaults to this week (or the last week once the season is over). Past weeks are
     * final and read only.
     */
    public function show(Request $request, League $league, ?int $week = null): View|RedirectResponse
    {
        if ($league->status() === LeagueStatus::Forming) {
            return redirect()->route('leagues.show', $league)->with('status', 'Matchups start at kickoff, '.$league->starts_on->format('l, F j').'.');
        }

        $latest = $this->latestWeek($league);
        $week ??= $latest;
        abort_unless($week >= 1 && $week <= $latest, 404);

        $table = new LeagueTable($league);
        $table->settle();
        $pager = new MatchupPager($table, $week, $request->user());
        $pages = $pager->pages();

        return view('leagues.matchups', [
            'league' => $league,
            'week' => $week,
            'latest' => $latest,
            'final' => $table->isFinal($week),
            'pages' => $pages,
            'cards' => array_column($pages, 'card'),
            'leagues' => MatchupPager::switchableLeagues($request->user()),
        ]);
    }

    /**
     * One task on the pager, as a sheet: details, proof (when the viewer may see it), the post shared from it, and
     * the actions. Opened in a bottom sheet with JavaScript; without it, a page of its own.
     */
    public function task(Request $request, League $league, int $week, RoadmapTask $task): View
    {
        abort_if($league->status() === LeagueStatus::Forming, 404);
        abort_unless($week >= 1 && $week <= $this->latestWeek($league), 404);

        $table = new LeagueTable($league);
        $pager = new MatchupPager($table, $week, $request->user());
        $found = $pager->locate($task);
        abort_if($found === null, 404);

        $task = $found['row']['task'];
        $task->loadMissing('proofRequests', 'disputes.disputer');
        $viewer = $request->user();
        $isOpponent = $found['opponent']?->user_id === $viewer->id;
        $final = $table->isFinal($week);

        $data = [
            'league' => $league,
            'week' => $week,
            'row' => $found['row'],
            'member' => $found['member'],
            'goalWeek' => $found['goalWeek'],
            'highlight' => $pager->highlightFor($task),
            'readOnly' => $final,
            // The matchup opponent and the league owner can dispute a check-in; never your own.
            'canDispute' => ! $final && $task->completed_at !== null && $found['member']->user_id !== $viewer->id
                && ($isOpponent || $league->commissioner_id === $viewer->id),
            'isOwner' => $found['member']->user_id === $viewer->id,
        ];

        return view($request->ajax() ? 'leagues.partials.task-sheet' : 'leagues.matchup-task', $data);
    }

    /**
     * This week, or the last week once the season is over.
     */
    private function latestWeek(League $league): int
    {
        return min($league->currentWeek() ?? $league->weeksCount(), $league->weeksCount());
    }
}
