<?php

namespace App;

enum HighlightKind: string
{
    case GoalCompleted = 'goal_completed';
    case GreatWeek = 'great_week';
    case MilestoneHit = 'milestone_hit';
    case CheckIn = 'check_in';

    /**
     * The badge shown on the highlight.
     */
    public function label(): string
    {
        return match ($this) {
            self::GoalCompleted => 'Crossed the GoalLine',
            self::GreatWeek => 'Great GoalWeek',
            self::MilestoneHit => 'Hit a milestone',
            self::CheckIn => 'Showed the work',
        };
    }
}
