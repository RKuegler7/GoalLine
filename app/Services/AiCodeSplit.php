<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\PlanRefinement;
use App\Models\RoadmapWeek;
use App\Models\WeekReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who did what on a plan, in plain words: the parts the AI coach drafted, and the parts code enforced, with live
 * examples from this goal. Without a goal it gives the general version for the About page.
 */
class AiCodeSplit
{
    /**
     * How many raised weeks are named before the rest are counted.
     */
    private const NAMED_RAISES = 2;

    /**
     * @return array{ai: list<string>, code: list<string>, sample: bool}
     */
    public static function for(?Goal $goal): array
    {
        return $goal === null ? self::general() : self::forGoal($goal);
    }

    /**
     * @return array{ai: list<string>, code: list<string>, sample: bool}
     */
    public static function general(): array
    {
        return [
            'ai' => [
                'Writes intake questions for your goal',
                'Drafts your GoalWeeks: a focus, a milestone and the tasks',
                'Explains the plan, and reworks it when you ask (you approve every change)',
                'Writes a weekly tip and a short recap summary from your numbers',
            ],
            'code' => [
                'Sets the minimum pace your deadline needs, and raises any week that falls short',
                'Locks each GoalWeek when it starts',
                'Scores every check-in, band and matchup',
                'Runs disputes by the rules, with people deciding',
                'Caps how much AI GoalLine can use each day',
            ],
            'sample' => false,
        ];
    }

    /**
     * @return array{ai: list<string>, code: list<string>, sample: bool}
     */
    public static function forGoal(Goal $goal): array
    {
        $goal->loadMissing('intake', 'weeks');
        $weekIds = $goal->weeks->modelKeys();
        $ai = [];
        $code = [];

        $intake = $goal->intake;
        $questions = count($intake?->questions ?? []);

        if ($intake?->source === GoalIntake::SOURCE_AI && $questions > 0) {
            $ai[] = "Wrote {$questions} intake ".Str::plural('question', $questions).' just for this goal.';
        } elseif ($intake?->source === GoalIntake::SOURCE_FALLBACK) {
            $code[] = 'Asked the built-in starter questions for this kind of goal.';
        }

        $stretches = $goal->weeks->count();

        if ($goal->plan_source === 'coach' && $stretches > 0) {
            $ai[] = "Drafted your {$stretches} ".Str::plural('GoalWeek', $stretches).': a focus, a milestone and the tasks for each.';
            if (filled($goal->roadmap_summary)) {
                $ai[] = 'Explained the plan in its own words.';
            }
        } elseif ($goal->plan_source === 'fallback') {
            $code[] = 'Built a starter plan from your goal\'s category when the coach was unavailable.';
        }

        // The three counts come back in one query, so the panel stays light on the goal page.
        $counts = DB::query()
            ->selectSub(PlanRefinement::query()->selectRaw('count(*)')->where('goal_id', $goal->id)->where('kind', PlanRefinement::REFINE)->where('status', PlanRefinement::ACCEPTED), 'refined')
            ->selectSub(WeekReview::query()->selectRaw('count(*)')->whereIn('roadmap_week_id', $weekIds)->whereNotNull('coach_tip'), 'tips')
            ->selectSub(Dispute::query()->selectRaw('count(*)')->whereHas('task', fn ($tasks) => $tasks->whereIn('roadmap_week_id', $weekIds)), 'disputes')
            ->first();
        $refined = (int) $counts->refined;

        if ($refined > 0) {
            $ai[] = 'Reworked your plan '.($refined === 1 ? 'once' : "{$refined} times").' when you asked. You approved each change.';
        }

        if ($goal->plan_verdict !== null) {
            $ai[] = 'Checked your edits against your deadline and suggested a fix you could take or leave.';
        }

        $tips = (int) $counts->tips;

        if ($tips > 0) {
            $ai[] = "Wrote {$tips} weekly ".Str::plural('tip', $tips).' after your reviews.';
        }

        $raised = $goal->weeks->filter(fn (RoadmapWeek $week) => filled($week->floor_note));

        if ($raised->isNotEmpty()) {
            foreach ($raised->take(self::NAMED_RAISES) as $week) {
                $code[] = "We raised {$week->label()} to your minimum pace.";
            }
            if ($raised->count() > self::NAMED_RAISES) {
                $more = $raised->count() - self::NAMED_RAISES;
                $code[] = "And {$more} more ".Str::plural('week', $more).', the same way.';
            }
        } elseif ($goal->weeks->contains(fn (RoadmapWeek $week) => $week->floor_points !== null)) {
            $code[] = 'Checked every GoalWeek against the minimum pace your deadline needs.';
        }

        $locked = $goal->weeks->filter(fn (RoadmapWeek $week) => $week->isStarted())->count();
        $code[] = $locked > 0
            ? "Locked {$locked} ".Str::plural('GoalWeek', $locked).' when '.($locked === 1 ? 'it' : 'they').' started, so the plan you\'re scored on can\'t move.'
            : 'Will lock each GoalWeek\'s plan when it starts.';

        $points = $goal->points();
        $code[] = "Scored every check-in: {$points} ".Str::plural('point', $points).' so far, by plain tested code.';

        $disputes = (int) $counts->disputes;
        $code[] = $disputes > 0
            ? "Ran {$disputes} ".Str::plural('dispute', $disputes).' by the rules, with people deciding. The AI never rules on one.'
            : 'Runs any dispute by the rules, with people deciding. The AI never rules on one.';

        $code[] = 'Capped how much AI GoalLine can use each day, so costs stay small.';

        return ['ai' => $ai, 'code' => $code, 'sample' => DemoCoach::applies($goal->user)];
    }
}
