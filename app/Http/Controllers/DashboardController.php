<?php

namespace App\Http\Controllers;

use App\Clock;
use App\GoalStatus;
use App\GoalWeekResult;
use App\HighlightKind;
use App\LeagueStatus;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\DailyStreak;
use App\Services\GoalDay;
use App\Services\HighlightFeed;
use App\Services\History;
use App\Services\KickoffChecklist;
use App\Services\LeagueSnapshot;
use App\Services\ProfileStats;
use App\Services\TodaysGameplan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * How many friend posts the Clubhouse shows at a time.
     */
    private const FRIEND_FEED_SIZE = 4;

    /**
     * The Clubhouse: Today's Gameplan, this GoalWeek across every goal, GoalLeagues, highlights, and the scoreboard.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($request->query('tab') === 'history') {
            $history = new History($user);

            return view('dashboard-history', [
                'goalDayPending' => app(GoalDay::class)->pending($user),
                'leagues' => $history->leagues(),
                'pastGoals' => $history->goals(),
                'weeks' => $history->weeks(),
            ]);
        }

        // Now: only what's in play. Goals past their date and finished seasons live in History.
        $goals = $user->goals()->inPlay()->with(['weeks.tasks', 'weeks.review', 'habits.logs', 'intake'])->orderBy('target_date')->get();
        $stats = new ProfileStats($user);
        $leagues = $user->leagues()->latest('starts_on')->get()->reject(fn (League $league) => $league->status() === LeagueStatus::Finished);
        $leagueGoalIds = $leagues->pluck('pivot.goal_id');

        return view('dashboard', [
            'goals' => $goals,
            'gameplan' => new TodaysGameplan($goals, $user),
            'streak' => new DailyStreak($user),
            'kickoff' => new KickoffChecklist($user),
            'stats' => $stats,
            'greeting' => $this->greeting(),
            'weekPoints' => $goals->sum(fn (Goal $goal) => $goal->currentWeek()?->result()?->points() ?? 0),
            'activity' => $this->weekActivity($user),
            'leagues' => $leagues->map(fn (League $league) => new LeagueSnapshot($league, $user))->values(),
            // Goals in play outside any current GoalLeague get "Play it with friends?", unless the member chose solo.
            'soloGoals' => $goals->reject(fn (Goal $goal) => $leagueGoalIds->contains($goal->id)),
            'leagueSetup' => $this->leagueSetup($leagues, $goals),
            'chosenSolo' => collect($request->session()->get('solo_goals', [])),
            'endedGoals' => $user->goals()->active()->get()->filter->hasEndedTimeline()->count(),
            'goalDayPending' => $goalDayPending = app(GoalDay::class)->pending($user)->first(),
            'goalDayUpcoming' => $goalDayPending ? null : app(GoalDay::class)->upcoming($user),
            'friendFeed' => (new HighlightFeed($user))->friends(self::FRIEND_FEED_SIZE),
            'shareable' => $this->shareableMoment($user),
        ]);
    }

    /**
     * Every live GoalLeague whose goal still needs the member: no goal to play (or it ended), the questions still open,
     * or the plan still being built. Nobody is left in a league with nowhere to see or set their goal.
     *
     * @param  Collection<int, League>  $leagues  Forming or in season, with the membership pivot.
     * @param  Collection<int, Goal>  $inPlay
     * @return list<array{league: League, state: string, goal: Goal|null, spare: Goal|null}>
     */
    private function leagueSetup(Collection $leagues, Collection $inPlay): array
    {
        $attached = $leagues->pluck('pivot.goal_id')->filter();
        $spare = $inPlay->first(fn (Goal $goal) => ! $attached->contains($goal->id));

        return $leagues->map(function (League $league) use ($inPlay, $spare) {
            $goal = $inPlay->firstWhere('id', $league->pivot->goal_id);
            $goal?->loadMissing('intake');

            $state = match (true) {
                $goal === null => 'needs_goal',
                $goal->intake?->isOpen() => 'setting_up',
                $goal->roadmap_status !== RoadmapStatus::Ready => 'building',
                default => null,
            };

            return $state === null ? null : ['league' => $league, 'state' => $state, 'goal' => $goal, 'spare' => $state === 'needs_goal' ? $spare : null];
        })->filter()->values()->all();
    }

    /**
     * "Good morning" on the person's own clock.
     */
    private function greeting(): string
    {
        $hour = now(Clock::timezone())->hour;

        return match (true) {
            $hour < 5 => 'Up late',
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Check-ins per day this week, Monday to Sunday, on the person's clock: the habit-tracker view.
     *
     * @return list<array{date: CarbonImmutable, count: int, isToday: bool, isFuture: bool}>
     */
    private function weekActivity(User $user): array
    {
        $today = CarbonImmutable::parse(Clock::today()->toDateString(), Clock::timezone());
        $monday = $today->startOfWeek(CarbonImmutable::MONDAY);

        $counts = RoadmapTask::query()
            ->whereNotNull('completed_at')
            ->whereHas('week.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->whereBetween('completed_at', [$monday->setTimezone(config('app.timezone')), $monday->addWeek()->setTimezone(config('app.timezone'))])
            ->pluck('completed_at')
            ->countBy(fn ($moment) => Clock::local($moment)->toDateString());

        $habitCounts = HabitLog::query()
            ->whereHas('habit.goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->whereDate('logged_on', '>=', $monday->toDateString())
            ->whereDate('logged_on', '<=', $monday->addDays(6)->toDateString())
            ->get()
            ->countBy(fn (HabitLog $log) => $log->logged_on->toDateString());

        return collect(range(0, 6))->map(function (int $offset) use ($monday, $today, $counts, $habitCounts) {
            $day = $monday->addDays($offset);

            return [
                'date' => $day,
                'count' => $counts->get($day->toDateString(), 0) + $habitCounts->get($day->toDateString(), 0),
                'isToday' => $day->isSameDay($today),
                'isFuture' => $day->gt($today),
            ];
        })->all();
    }

    /**
     * A recent moment worth sharing that hasn't been shared yet: a finished goal or a great GoalWeek.
     *
     * @return array{label: string, url: string}|null
     */
    private function shareableMoment(User $user): ?array
    {
        $shared = $user->highlights()->get(['kind', 'goal_id', 'roadmap_week_id']);

        $finished = $user->goals()->where('status', GoalStatus::Completed)->where('completed_at', '>=', now()->subDays(30))->latest('completed_at')->get()
            ->first(fn (Goal $goal) => ! $shared->contains(fn ($highlight) => $highlight->kind === HighlightKind::GoalCompleted && $highlight->goal_id === $goal->id));

        if ($finished) {
            return ['label' => "You crossed the line on \u{201C}{$finished->title}\u{201D}. Share it?", 'url' => route('highlights.create', ['goal' => $finished])];
        }

        $greatWeek = RoadmapWeek::query()
            ->whereHas('goal', fn ($goals) => $goals->where('user_id', $user->id))
            ->where('ends_on', '>=', Clock::today()->subDays(14))
            ->where('starts_on', '<=', Clock::today())
            ->with(['goal.weeks', 'goal.habits.logs', 'tasks'])
            ->latest('ends_on')
            ->get()
            ->first(fn (RoadmapWeek $week) => in_array($week->result(), [GoalWeekResult::Past, GoalWeekResult::Smashed], true)
                && ! $shared->contains(fn ($highlight) => $highlight->kind === HighlightKind::GreatWeek && $highlight->roadmap_week_id === $week->id));

        return $greatWeek
            ? ['label' => "{$greatWeek->result()->label()} in {$greatWeek->label()} of \u{201C}{$greatWeek->goal->title}\u{201D}. Share it?", 'url' => route('highlights.create', ['week' => $greatWeek])]
            : null;
    }
}
