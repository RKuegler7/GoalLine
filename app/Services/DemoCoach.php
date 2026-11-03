<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\PlanVerdict;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Saved sample answers for the coach in demo worlds, so the demo never waits on the API, never fails, and never
 * spends money. Set DEMO_LIVE_AI=true to let demo visitors use the real coach (within the demo's small allowance).
 * Every sample goes through the same checks as a real answer.
 */
class DemoCoach
{
    public const PLAN_SUMMARY = 'In the demo, the coach uses a saved sample plan: start steady, build in the middle, and finish fresh. Every week is checked against the minimum pace your deadline needs.';

    /**
     * Whether this person gets the saved samples instead of the real coach.
     */
    public static function applies(?User $user): bool
    {
        return (bool) $user?->isDemo() && ! config('goalline.demo.live_ai');
    }

    /**
     * A sample refinement: the first upcoming week gets lighter, and its last task moves to the week after.
     *
     * @param  Collection<int, RoadmapWeek>  $editable
     * @return array{explanation: string, weeks: list<array{position: int, focus: string, milestone: string, tasks: list<string>}>}
     */
    public static function refine(Collection $editable): array
    {
        $plan = fn (RoadmapWeek $week) => $week->tasks->filter(fn (RoadmapTask $task) => PlanEditor::isPlanTask($task))->sortBy('position')->pluck('description')->values();
        $first = $editable->first();
        $next = $editable->get(1);
        $firstTasks = $plan($first);
        $moved = $next && $firstTasks->count() > 1 ? $firstTasks->pop() : null;

        $weeks = [[
            'position' => $first->position,
            'focus' => Str::limit($first->focus, 250, ''),
            'milestone' => $first->milestone,
            'tasks' => [...$firstTasks->all(), 'Light review session, 20 minutes'],
        ]];

        if ($moved) {
            $weeks[] = ['position' => $next->position, 'focus' => $next->focus, 'milestone' => $next->milestone, 'tasks' => [...$plan($next)->all(), $moved]];
        }

        return [
            'explanation' => $moved
                ? "This is the demo coach's saved sample: {$first->label()} gets lighter, and \u{201C}{$moved}\u{201D} moves to {$next->label()} so nothing is dropped."
                : "This is the demo coach's saved sample: {$first->label()} gets a light review session.",
            'weeks' => $weeks,
        ];
    }

    /**
     * A sample coach check after an edit.
     *
     * @return array{verdict: PlanVerdict, note: string, suggestion: string, fix: null}
     */
    public static function review(): array
    {
        return [
            'verdict' => PlanVerdict::OnTrack,
            'note' => 'Demo coach: your change keeps the plan on pace for the deadline.',
            'suggestion' => 'Keep showing up. One more day on the line.',
            'fix' => null,
        ];
    }

    public static function tip(): string
    {
        return 'Demo coach: your best days started early. Put tomorrow\'s hardest task first, and protect that slot like a meeting.';
    }

    public static function caption(RoadmapTask $task): string
    {
        return Str::limit("Showing the process: {$task->description}. One more day on the line.", 280);
    }

    /**
     * A sample plan in the shape the coach returns, from the goal's category.
     *
     * @param  list<array{starts_on: CarbonInterface, ends_on: CarbonInterface}>  $periods
     * @return array{summary: string, habits: list<array{title: string, times_per_week: int}>, periods: list<array{focus: string, milestone: string, tasks: list<string>}>}
     */
    public static function plan(Goal $goal, array $periods): array
    {
        return ['summary' => self::PLAN_SUMMARY, ...array_diff_key(FallbackPlan::for($goal, $periods), ['summary' => true])];
    }
}
