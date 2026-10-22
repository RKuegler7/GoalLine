<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\League;
use App\Models\LeagueMessage;
use App\Services\LeagueTable;
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
        $leagues = $request->user()->leagues()->with('members')->latest('starts_on')->get();

        return view('leagues.index', ['leagues' => $leagues]);
    }

    /**
     * Show the form for starting a league.
     */
    public function create(Request $request): View
    {
        return view('leagues.create', [
            'goals' => $request->user()->goals()->active()->orderBy('target_date')->get(),
            'kickoffs' => self::kickoffOptions(),
        ]);
    }

    /**
     * Start a league, with its creator as commissioner and first member.
     */
    public function store(Request $request): RedirectResponse
    {
        $kickoffs = collect(self::kickoffOptions())->map->toDateString()->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:280'],
            'starts_on' => ['required', Rule::in($kickoffs)],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'goal_id' => ['required', Rule::exists('goals', 'id')->where('user_id', $request->user()->id)->where('status', 'active')],
        ], [
            'starts_on.in' => 'Pick one of the upcoming Mondays.',
            'goal_id.required' => 'Pick the goal you will play in this league.',
            'goal_id.exists' => 'Pick one of your active goals.',
        ]);

        // Seasons end on a Sunday, so every league week is a full Monday-to-Sunday week.
        $startsOn = CarbonImmutable::parse($validated['starts_on']);
        $endsOn = CarbonImmutable::parse($validated['ends_on']);
        $endsOn = $endsOn->isSunday() ? $endsOn : $endsOn->next(CarbonImmutable::SUNDAY);
        $endsOn = $endsOn->min($startsOn->addWeeks(League::MAX_WEEKS)->subDay());

        $league = DB::transaction(function () use ($request, $validated, $startsOn, $endsOn) {
            $league = new League([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
            $league->commissioner()->associate($request->user())->save();
            $league->members()->create(['user_id' => $request->user()->id, 'goal_id' => $validated['goal_id']]);
            $league->announce($request->user()->name.' started the league. Kickoff is '.$startsOn->format('l, F j').'.');

            return $league;
        });

        return redirect()->route('leagues.show', $league)->with('status', 'League created. Share your invite code to bring people in.');
    }

    /**
     * The league: invite, GoalGroup matchups, standings, and past weeks.
     */
    public function show(Request $request, League $league): View
    {
        $table = new LeagueTable($league);
        $table->settle();

        // Opening the league counts as reading its chat.
        $league->memberFor($request->user())->update(['last_read_message_id' => $league->messages()->max('id')]);

        return view('leagues.show', [
            'league' => $league,
            'table' => $table,
            'me' => $league->memberFor($request->user()),
            'myGoals' => $request->user()->goals()->active()->orderBy('target_date')->get(),
            'messages' => $league->messages()->with('user')->latest('id')->limit(LeagueMessage::RECENT_COUNT)->get()->reverse()->values(),
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
