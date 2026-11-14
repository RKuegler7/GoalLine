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
            // One real question as the example. Only the question: the answers are the member's own and never shown here.
            $example = Str::limit((string) ($intake->questions[0]['text'] ?? ''), 90);
            $ai[] = "Wrote {$questions} intake ".Str::plural('question', $questions).' just for this goal'.($example !== '' ? ", like \u{201C}{$example}\u{201D}" : '').'.';
        } elseif ($intake?->source === GoalIntake::SOURCE_FALLBACK) {
            $code[] = 'Asked the built-in starter questions for this kind of goal.';
        }

        $stretches = $goal->weeks->count();

        if ($goal->plan_source === 'coach' && $stretches > 0) {
            $ai[] = "Drafted your {$stretches} ".Str::plural('GoalWeek', $stretches).': a focus, a milestone and the tasks for each.';
            if (filled($goal->roadmap_summary)) {
                $ai[] = "Explained the plan in its own words: \u{201C}".Str::limit((string) $goal->roadmap_summary, 110)."\u{201D}";
            }
            $code[] = "Set the dates of all {$stretches} ".Str::plural('GoalWeek', $stretches).' from your start and deadline, and kept the coach\'s draft only because its weeks matched them.';
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
                $added = preg_match('/\(\+(\d+) points?\)/', (string) $week->floor_note, $match) ? ' (+'.$match[1].' '.Str::plural('point', (int) $match[1]).')' : '';
                $code[] = "We raised {$week->label()} to your minimum pace of {$week->floor_points} ".Str::plural('point', (int) $week->floor_points)."{$added}.";
            }
            if ($raised->count() > self::NAMED_RAISES) {
                $more = $raised->count() - self::NAMED_RAISES;
                $code[] = "And {$more} more ".Str::plural('week', $more).', the same way.';
            }
        } elseif ($goal->weeks->contains(fn (RoadmapWeek $week) => $week->floor_points !== null)) {
            $code[] = 'Checked every GoalWeek against the minimum pace your deadline needs.';
        }

        $started = $goal->weeks->filter(fn (RoadmapWeek $week) => $week->isStarted());
        $latest = $started->sortBy('starts_on')->last();
        $next = $goal->weeks->reject(fn (RoadmapWeek $week) => $week->isStarted())->sortBy('starts_on')->first();
        $code[] = match (true) {
            $latest !== null => "Locked {$latest->label()} at the start of ".$latest->starts_on->format('l, M j').($started->count() > 1 ? ', and the '.($started->count() - 1).' before it,' : ',').' so the plan you\'re scored on can\'t move.',
            $next !== null => "Will lock {$next->label()} at the start of ".$next->starts_on->format('l, M j').', so the plan you\'re scored on can\'t move.',
            default => 'Locks each GoalWeek\'s plan when it starts.',
        };

        $points = $goal->points();
        $code[] = "Scored every check-in: {$points} ".Str::plural('point', $points).' so far, by plain tested code.';

        $disputes = (int) $counts->disputes;
        $code[] = $disputes > 0
            ? "Ran {$disputes} ".Str::plural('dispute', $disputes).' by the rules, with people deciding. The AI never rules on one.'
            : 'Runs any dispute by the rules, with people deciding. The AI never rules on one.';

        $code[] = 'Capped AI use at '.config('goalline.ai.daily_requests_per_member').' coaching requests per member a day, and $'.rtrim(rtrim(number_format((float) config('goalline.ai.daily_budget_usd'), 2), '0'), '.').' a day for all of GoalLine.';

        return ['ai' => $ai, 'code' => $code, 'sample' => DemoCoach::applies($goal->user)];
    }
}
