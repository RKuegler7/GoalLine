<?php

namespace App;

/**
 * Examples and suggestions that match the kind of goal, so no form ever suggests an unrelated goal: placeholder
 * titles and target results, and "Make it yours" chips that add a line of context for the intake questions and the
 * plan. The chips are planning context, not medical advice.
 */
class GoalExamples
{
    /**
     * A placeholder title for this kind of goal.
     */
    public static function title(?GoalCategory $category): string
    {
        return match ($category) {
            GoalCategory::Academic => 'Pass my organic chemistry final by May 8',
            GoalCategory::Career => 'Land a new analyst role by June 30',
            GoalCategory::Fitness => 'Run a half marathon by April 12',
            GoalCategory::Creative => 'Finish the first draft of my novel by August 1',
            GoalCategory::Personal => 'Hold a 10-minute conversation in Spanish by July 1',
            default => 'Name your goal and the date it happens by',
        };
    }

    /**
     * A placeholder for the optional target result.
     */
    public static function result(?GoalCategory $category): string
    {
        return match ($category) {
            GoalCategory::Academic => 'An A, or 90% or better',
            GoalCategory::Career => 'Three offers to compare',
            GoalCategory::Fitness => 'A finish time, like 4:15',
            GoalCategory::Creative => '60,000 words',
            GoalCategory::Personal => 'Every day for 30 days',
            default => 'What "done" looks like',
        };
    }

    /**
     * "Make it yours" chips: a short label and the sentence it adds to the goal's context.
     *
     * @return array<string, string>
     */
    public static function chips(?GoalCategory $category): array
    {
        return match ($category) {
            GoalCategory::Fitness => [
                'First time?' => 'This is my first time going for a goal like this.',
                'Aim for a finish time' => 'I want to aim for a specific finish time or result.',
                'Fewer training days' => 'I can only train a few days a week.',
                'Return from injury' => 'I am coming back from an injury, so I want a careful build-up.',
            ],
            GoalCategory::Academic => [
                'Starting from behind' => 'I am starting from behind and need to catch up first.',
                'Exam heavy' => 'Most of my grade comes from exams.',
                'Evenings only' => 'I can mostly study in the evenings.',
                'Aim for top marks' => 'I want to aim for top marks, not just a pass.',
            ],
            GoalCategory::Career => [
                'Changing fields' => 'I am changing fields, so I need to build new skills.',
                'Busy schedule' => 'I have a full-time schedule and limited hours each week.',
                'Networking first' => 'I want to focus on networking and conversations.',
                'Certification' => 'There is a certification or exam involved.',
            ],
            GoalCategory::Creative => [
                'First big project' => 'This is my first project of this size.',
                'Short sessions' => 'I work best in short, regular sessions.',
                'Ship it publicly' => 'I want to share or publish it when it is done.',
                'Restarting' => 'I am restarting a project I set aside.',
            ],
            default => [
                'First time?' => 'This is my first time going for a goal like this.',
                'Busy weeks' => 'Some weeks will be busy, so I want a steady pace.',
                'Daily habit' => 'I want this to become a daily habit.',
                'Restarting' => 'I am picking this back up after a break.',
            ],
        };
    }
}
