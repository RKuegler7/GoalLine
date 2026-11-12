<?php

namespace App;

use App\Services\ScoringRules;

/**
 * Season medals. Code awards every one of them from settled results (see MedalAwarder and config/medals.php).
 */
enum MedalType: string
{
    case GoalAchieved = 'goal_achieved';
    case TableChampion = 'table_champion';
    case Podium = 'podium';
    case PerfectWeek = 'perfect_week';
    case SmashedIt = 'smashed_it';
    case Streak = 'streak';
    case Consistency = 'consistency';
    case ProofPro = 'proof_pro';
    case PhotoFinishWinner = 'photo_finish_winner';
    case Comeback = 'comeback';
    case Encourager = 'encourager';
    case BestWeek = 'best_week';

    /**
     * The medal's name.
     */
    public function label(): string
    {
        return match ($this) {
            self::GoalAchieved => 'Goal Achieved',
            self::TableChampion => 'Table Champion',
            self::Podium => 'Podium',
            self::PerfectWeek => 'Perfect Week',
            self::SmashedIt => 'Smashed It',
            self::Streak => 'Streak',
            self::Consistency => 'Consistency',
            self::ProofPro => 'Proof Pro',
            self::PhotoFinishWinner => 'Photo Finish Winner',
            self::Comeback => 'Comeback',
            self::Encourager => 'Encourager',
            self::BestWeek => 'Best Week',
        };
    }

    /**
     * How you earn it, in one line (also used in Help).
     */
    public function rule(): string
    {
        $medals = config('medals');

        return match ($this) {
            self::GoalAchieved => 'You said "I did it" on Goal Day. With proof or a league mate\'s confirm it\'s Verified.',
            self::TableChampion => 'Finished first in your league table.',
            self::Podium => 'Finished in the top '.$medals['podium_places'].' of your league table.',
            self::PerfectWeek => 'A '.app(ScoringRules::class)->weekRange()['max'].'-star GoalWeek: Smashed and won the matchup.',
            self::SmashedIt => 'Your first Smashed GoalWeek, and again at '.$medals['smashed_many'].' Smashed weeks.',
            self::Streak => 'GoalWeeks in line or better in a row: '.implode(', ', array_keys($medals['streak_tiers'])).'.',
            self::Consistency => 'In line or better in at least '.round($medals['consistency_share'] * 100).'% of your weeks (at least '.$medals['consistency_min_weeks'].').',
            self::ProofPro => $medals['proof_pro_checkins'].'+ check-ins with proof on one goal.',
            self::PhotoFinishWinner => 'Won '.$medals['photo_finish_wins'].'+ photo finishes in a season.',
            self::Comeback => 'Climbed the table from halfway through the season to the end.',
            self::Encourager => 'Gave '.$medals['encourager_cheers'].'+ cheers to league mates during the season.',
            self::BestWeek => 'The most stars in a single week in your league (ties share it).',
        };
    }

    /**
     * The medal's symbol, for the shelf and share card.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::GoalAchieved => '🏁',
            self::TableChampion => '🏆',
            self::Podium => '🥇',
            self::PerfectWeek => '⭐',
            self::SmashedIt => '💥',
            self::Streak => '🔥',
            self::Consistency => '📈',
            self::ProofPro => '📸',
            self::PhotoFinishWinner => '📷',
            self::Comeback => '🚀',
            self::Encourager => '👏',
            self::BestWeek => '🌟',
        };
    }
}
