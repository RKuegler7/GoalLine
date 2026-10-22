<?php

namespace App\Http\Controllers;

use App\Clock;
use App\GoalStatus;
use App\GoalWeekResult;
use App\HighlightKind;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Services\DailyStreak;
use App\Services\HighlightFeed;
use App\Services\LeagueSnapshot;
use App\Services\ProfileStats;
use App\Services\TodaysGameplan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The Clubhouse: Today's Gameplan, this GoalWeek across every goal, GoalLeagues, highlights, and the scoreboard.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $goals = $user->goals()->active()->with(['weeks.tasks', 'weeks.review', 'habits.logs'])->orderBy('target_date')->get();
        $stats = new ProfileStats($user);

        return view('dashboard', [
            'goals' => $goals,
            'gameplan' => new TodaysGameplan($goals),
            'streak' => new DailyStreak($user),
            'stats' => $stats,
            'greeting' => $this->greeting(),
            'weekPoints' => $goals->sum(fn (Goal $goal) => $goal->currentWeek()?->result()?->points() ?? 0),
            'activity' => $this->weekActivity($user),
            'leagues' => $user->leagues()->latest('starts_on')->get()->map(fn ($league) => new LeagueSnapshot($league, $user)),
            'highlights' => (new HighlightFeed($user))->latest(3),
            'shareable' => $this->shareableMoment($user),
            'retired' => $user->goals()->where('status', GoalStatus::Retired)->latest('retired_at')->get(),
        ]);
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
            ->with(['goal.weeks', 'tasks'])
            ->latest('ends_on')
            ->get()
            ->first(fn (RoadmapWeek $week) => in_array($week->result(), [GoalWeekResult::Past, GoalWeekResult::Smashed], true)
                && ! $shared->contains(fn ($highlight) => $highlight->kind === HighlightKind::GreatWeek && $highlight->roadmap_week_id === $week->id));

        return $greatWeek
            ? ['label' => "{$greatWeek->result()->label()} in {$greatWeek->label()} of \u{201C}{$greatWeek->goal->title}\u{201D}. Share it?", 'url' => route('highlights.create', ['week' => $greatWeek])]
            : null;
    }
}
