<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reporter_id', 'league_id', 'reason'])]
class PostReport extends Model
{
    /**
     * Longest reason, in characters.
     */
    public const MAX_REASON = 280;

    /**
     * The post reported.
     *
     * @return BelongsTo<Highlight, $this>
     */
    public function highlight(): BelongsTo
    {
        return $this->belongsTo(Highlight::class);
    }

    /**
     * Who reported it. The post is hidden from them.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * The GoalLeague whose owner reviews it, if the post was shared to one.
     *
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
