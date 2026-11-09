<?php

namespace App\Http\Controllers;

use App\Clock;
use App\GoalCategory;
use App\LeagueStatus;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\LeagueMessage;
use App\Models\PostReport;
use App\Services\GroupTasks;
use App\Services\LeagueSnapshot;
use App\Services\LeagueTable;
use App\Services\MatchupBoard;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeagueController extends Controller
{
    /**
     * The person's leagues, plus ways to start or join one.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $leagues = $user->leagues()->with('members.goal.weeks.tasks', 'members.goal.habits.logs', 'members.user')->latest('starts_on')->get();

        return view('leagues.index', [
            'leagues' => $leagues,
            // Your leagues first, with this week's band, record and matchup at a glance. In-season ones lead.
            'snapshots' => $leagues
                ->sortBy(fn (League $league) => match ($league->status()) {
                    LeagueStatus::Active => 0,
                    LeagueStatus::Forming => 1,
                    LeagueStatus::Finished => 2,
                })
                ->map(fn (League $league) => new LeagueSnapshot($league, $user))
                ->values(),
            'invites' => LeagueInvite::usable()->where('invitee_id', $user->id)->with('league.commissioner')->latest()->get(),
            'tab' => in_array($request->query('tab'), ['leagues', 'challenges', 'invites'], true) ? $request->query('tab') : 'leagues',
            'challenges' => $request->user()->challenges()
                ->withCount(['members', 'checkIns as my_days' => fn ($checkIns) => $checkIns->where('user_id', $request->user()->id)])
                ->withExists(['checkIns as checked_today' => fn ($checkIns) => $checkIns->where('user_id', $request->user()->id)->whereDate('checked_on', Clock::today()->toDateString())])
                ->orderByDesc('ends_on')
                ->get(),
        ]);
    }

    /**
     * Show the form for starting a league.
     */
    public function create(Request $request): View
    {
        $goals = $request->user()->goals()->active()->orderBy('target_date')->get();

        return view('leagues.create', [
            'goals' => $goals,
            'kickoffs' => self::kickoffOptions(),
            // Coming from "Play it with friends?": fill the league in from that goal.
            'fromGoal' => $goals->firstWhere('id', $request->integer('goal')),
        ]);
    }

    /**
     * Start a league, with its creator as commissioner and first member. The creator plays one of their goals or a
     * new one named right here; a new goal gets its intake questions next, then the invite section.
     */
    public function store(Request $request): RedirectResponse
    {
        $kickoffs = collect(self::kickoffOptions())->map->toDateString()->all();
        $newGoal = $request->input('goal_id') === 'new';

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:280'],
            'starts_on' => ['required', Rule::in($kickoffs)],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'goal_id' => ['required', $newGoal ? 'in:new' : Rule::exists('goals', 'id')->where('user_id', $request->user()->id)->where('status', 'active')],
            'goal_title' => [Rule::requiredIf($newGoal), 'nullable', 'string', 'max:120'],
            'goal_target_date' => [Rule::requiredIf($newGoal), 'nullable', 'date', 'after:'.Clock::today()->toDateString()],
            'category' => ['nullable', Rule::enum(GoalCategory::class)],
        ], [
            'starts_on.in' => 'Pick one of the upcoming Mondays.',
            'goal_id.required' => 'Pick the goal you will play in this league.',
            'goal_id.exists' => 'Pick one of your active goals.',
            'goal_title.required' => 'Name the goal you will play in this league.',
            'goal_target_date.required' => 'Set the date your goal needs to happen by.',
            'goal_target_date.after' => 'Pick a date after today.',
        ]);

        $goal = $newGoal
            ? $request->user()->goals()->create([
                'title' => $validated['goal_title'],
                'category' => $validated['category'] ?? GoalCategory::Other,
                'target_date' => $validated['goal_target_date'],
            ])
            : Goal::find($validated['goal_id']);

        // Seasons end on a Sunday, so every league week is a full Monday-to-Sunday week.
        $startsOn = CarbonImmutable::parse($validated['starts_on']);
        $endsOn = CarbonImmutable::parse($validated['ends_on']);
        $endsOn = $endsOn->isSunday() ? $endsOn : $endsOn->next(CarbonImmutable::SUNDAY);
        $endsOn = $endsOn->min($startsOn->addWeeks(League::MAX_WEEKS)->subDay());

        $league = DB::transaction(function () use ($request, $validated, $startsOn, $endsOn, $goal) {
            $league = new League([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? $goal->category,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
            $league->commissioner()->associate($request->user())->save();
            $league->members()->create(['user_id' => $request->user()->id, 'goal_id' => $goal->id]);
            $league->announce($request->user()->name.' started the league. Kickoff is '.$startsOn->format('l, F j').'.');

            return $league;
        });

        if ($newGoal) {
            GoalIntake::begin($goal);

            return redirect()->route('goals.intake', ['goal' => $goal, 'from' => 'league_home'])
                ->with('status', 'League created. A few questions about your goal, then you\'ll invite your people.');
        }

        return redirect()->to(route('leagues.show', $league).'#invite')->with('status', 'League created. Now invite your people: share the link, the code, or send invites below.');
    }

    /**
     * The league: this GoalWeek's matchup, your league goal, group tasks, standings, past weeks, invites, and chat.
     */
    public function show(Request $request, League $league, GroupTasks $groupTasks): View
    {
        // Votes whose 24 hours are up are settled before showing them.
        $groupTasks->closeDue($league);

        $table = new LeagueTable($league);
        $table->settle();

        // Opening the league counts as reading its chat.
        $me = $league->memberFor($request->user());
        $me->update(['last_read_message_id' => $league->messages()->max('id')]);

        // This GoalWeek's matchup (the last one once the season is over), for the card at the top.
        $matchupWeek = $league->status() === LeagueStatus::Forming ? null : min($league->currentWeek() ?? $league->weeksCount(), $league->weeksCount());
        $cards = $matchupWeek ? (new MatchupBoard($table, $matchupWeek))->cards($me) : [];

        $groupTaskWeeks = collect($groupTasks->proposableWeeks($league))->mapWithKeys(fn (int $week) => [$week => $groupTasks->slotsLeft($league, $week)]);

        return view('leagues.show', [
            'league' => $league,
            'table' => $table,
            'matchupWeek' => $matchupWeek,
            'matchupFinal' => $matchupWeek && $table->isFinal($matchupWeek),
            'matchupCount' => count($cards),
            'myCard' => collect($cards)->firstWhere('mine', true),
            'me' => $league->memberFor($request->user()),
            'myGoals' => $request->user()->goals()->active()->orderBy('target_date')->get(),
            'messages' => $league->messages()->with('user')->latest('id')->limit(LeagueMessage::RECENT_COUNT)->get()->reverse()->values(),
            'shareInvite' => $league->shareableInvite(),
            'pendingInvites' => $league->commissioner_id === $request->user()->id
                ? $league->invites()->usable()->where('kind', '!=', LeagueInvite::LINK)->with('invitee')->latest('id')->get()
                : collect(),
            'groupTasks' => $league->groupTasks()->with('votes', 'proposer')->latest('id')->limit(12)->get(),
            'groupTaskWeeks' => $groupTaskWeeks,
            // A typical GoalWeek's planned points, so each group task size shows what share of a week it is.
            'typicalPoints' => $table->typicalPlannedPoints($groupTaskWeeks->filter()->keys()->first() ?? $matchupWeek ?? 1),
            'groupTasksEnabled' => $groupTasks->isEnabledFor($league),
            'reportedPosts' => $league->commissioner_id === $request->user()->id
                ? PostReport::where('league_id', $league->id)->whereHas('highlight')->distinct()->count('highlight_id')
                : 0,
        ]);
    }

    /**
     * Call off a league before kickoff.
     */
    public function destroy(League $league): RedirectResponse
    {
        $league->delete();

        return redirect()->route('leagues.index')->with('status', 'League called off.');
    }

    /**
     * The next eight Mondays a season can kick off.
     *
     * @return list<CarbonImmutable>
     */
    public static function kickoffOptions(): array
    {
        $first = Clock::today()->toImmutable()->next(CarbonImmutable::MONDAY);

        return array_map(fn (int $weeks) => $first->addWeeks($weeks), range(0, 7));
    }
}
