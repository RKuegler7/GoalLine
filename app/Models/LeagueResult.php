<?php

namespace App\Models;

use App\GoalWeekResult;
use App\MatchupOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'week', 'result', 'result_points', 'opponent_id', 'vs_median', 'outcome', 'matchup_points'])]
class LeagueResult extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => GoalWeekResult::class,
            'outcome' => MatchupOutcome::class,
            'vs_median' => 'boolean',
        ];
    }

    /**
     * The league this result counts in.
     *
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * The member whose result this is.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The opponent faced, or null when playing the league median.
     *
     * @return BelongsTo<User, $this>
     */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }
}
