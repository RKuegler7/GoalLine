<?php

/**
 * Every medal rule in one place. Medals are computed by code from settled results (App\Services\MedalAwarder);
 * they never change stars or standings.
 */
return [
    // Top finishes in the league table that earn a Podium medal (gold, silver, bronze).
    'podium_places' => 3,

    // Smashed It: the first Smashed week earns it, and this many earn the "3+" tier.
    'smashed_many' => 3,

    // Streak: consecutive GoalWeeks In line or better => tier name.
    'streak_tiers' => [4 => 'bronze', 8 => 'silver', 12 => 'gold'],

    // Consistency: share of settled weeks In line or better, with a minimum number of weeks played.
    'consistency_share' => 0.90,
    'consistency_min_weeks' => 4,

    // Proof Pro: check-ins with proof on one goal.
    'proof_pro_checkins' => 10,

    // Photo Finish Winner: photo finish wins in a season.
    'photo_finish_wins' => 3,

    // Encourager: cheers given to league mates' posts during the season.
    'encourager_cheers' => 10,

    // Which medal is someone's "best" (for the share card and the standings sheet), most special first.
    'order' => [
        'goal_achieved', 'table_champion', 'podium', 'perfect_week', 'streak', 'consistency', 'smashed_it',
        'comeback', 'photo_finish_winner', 'best_week', 'proof_pro', 'encourager',
    ],
];
