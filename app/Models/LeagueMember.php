<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'goal_id', 'last_read_message_id'])]
class LeagueMember extends Model
{
    /**
     * The league joined.
     *
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * The person in the league.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The goal this member plays in the league.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Chat messages from others posted since this member last opened the league.
     */
    public function unreadCount(): int
    {
        return $this->league->messages()
            ->where('id', '>', $this->last_read_message_id ?? 0)
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $this->user_id))
            ->count();
    }
}
