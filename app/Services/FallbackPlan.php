<?php

namespace App\Services;

use App\GoalCategory;
use App\Models\Goal;
use Carbon\CarbonInterface;

/**
 * A starter plan built from the goal's category, for when the coach can't build one (the API failed, returned
 * something unusable, or an AI cap was reached). It follows the usual shape of the field (start, build, finish),
 * and the plan floor raises every week to the minimum pace after it's saved. The person is told plainly.
 */
class FallbackPlan
{
    public const SUMMARY = "The coach couldn't build a custom plan just now, so this is a starter plan from your goal type, paced to the minimum your deadline needs. Edit any week, or tap \u{201C}Try the coach again\u{201D}.";

    /**
     * Phases by category: [focus, milestone, tasks] for the start, middle, and end of the plan.
     */
    private const PHASES = [
        'academic' => [
            ['Map the material and start strong', 'Finish the first unit and its practice questions', ['List every topic the exam or course covers', 'Study the first unit for two focused sessions', 'Do a set of practice questions on it', 'Review what you missed']],
            ['Work through the material', 'Score 70% or better on this stretch\'s practice set', ['Study the next unit for two focused sessions', 'Do a timed practice set', 'Review every missed question', 'Make summary notes for the unit']],
            ['Practice exams and final review', 'Take a full practice exam under real conditions', ['Take a full practice exam', 'Review your weakest areas', 'Redo questions you got wrong', 'Rest well before the day']],
        ],
        'fitness' => [
            ['Build the base gently', 'Complete every session this week without pain', ['Easy session at a conversational pace', 'Strength and mobility, 20 minutes', 'Easy session, a little longer', 'Rest day: walk or stretch']],
            ['Build steadily', 'Finish this week\'s longest session', ['Easy session', 'One harder session at a steady effort', 'Strength and mobility, 20 minutes', 'Longest session of the week']],
            ['Sharpen and taper', 'Arrive rested and ready', ['Short, easy sessions to stay fresh', 'One short session at goal effort', 'Mobility and stretching', 'Rest the day before']],
        ],
        'default' => [
            ['Get started', 'Finish the first concrete step toward the goal', ['Write down exactly what done looks like', 'Do the first focused session on it', 'Do a second focused session', 'Review the week and plan the next']],
            ['Build momentum', 'Complete this stretch\'s main piece of work', ['Two focused sessions on the main work', 'Ask someone for feedback or help', 'Fix what the feedback found', 'Review the week and plan the next']],
            ['Finish strong', 'Have everything ready to call the goal done', ['Finish the last open pieces', 'Check the work against what done looks like', 'Tidy up loose ends', 'Celebrate the process']],
        ],
    ];

    /**
     * A plan with one entry per stretch, in the same shape the coach returns.
     *
     * @param  list<array{starts_on: CarbonInterface, ends_on: CarbonInterface}>  $periods
     * @return array{summary: string, habits: list<array{title: string, times_per_week: int}>, periods: list<array{focus: string, milestone: string, tasks: list<string>}>}
     */
    public static function for(Goal $goal, array $periods): array
    {
        $phases = self::PHASES[$goal->category->value] ?? self::PHASES['default'];
        $count = count($periods);

        return [
            'summary' => self::SUMMARY,
            'habits' => [],
            'periods' => array_map(function (int $index) use ($phases, $count, $goal) {
                $phase = $phases[match (true) {
                    $count <= 1 || $index === $count - 1 => 2,
                    $index < max(1, intdiv($count, 3)) => 0,
                    default => 1,
                }];

                // A physical goal that isn't in the fitness category still gets rest days.
                $tasks = $goal->isPhysical() && $goal->category !== GoalCategory::Fitness ? [...$phase[2], 'Rest day'] : $phase[2];

                return ['focus' => $phase[0], 'milestone' => $phase[1], 'tasks' => $tasks];
            }, array_keys($periods)),
        ];
    }
}
