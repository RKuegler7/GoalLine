<?php

namespace App;

enum HighlightKind: string
{
    case GoalCompleted = 'goal_completed';
    case GreatWeek = 'great_week';
    case MilestoneHit = 'milestone_hit';
    case CheckIn = 'check_in';
    case WeekCard = 'week_card';
    case GoalDay = 'goal_day';
    case SeasonCard = 'season_card';

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
            self::WeekCard => 'Weekly result',
            self::GoalDay => 'Goal Day',
            self::SeasonCard => 'Season finale',
        };
    }
}
