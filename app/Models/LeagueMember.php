<?php

namespace App\Models;

use App\RoadmapStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'goal_id', 'last_read_message_id'])]
class LeagueMember extends Model
{
    public const MAX_TEAM_NAME = 24;

    public const GOAL_SET = 'set';

    public const GOAL_SETTING_UP = 'setting_up';

    public const GOAL_NOT_STARTED = 'not_started';

    /**
     * Where the member's league goal stands, for the owner's list: set (questions answered and plan ready), setting
     * up (a goal, but the questions or plan aren't done), or not started (no active goal to play).
     */
    public function goalStatus(): string
    {
        $goal = $this->goal;

        return match (true) {
            $goal === null || ! $goal->isActive() => self::GOAL_NOT_STARTED,
            $goal->intake?->isOpen() || $goal->roadmap_status !== RoadmapStatus::Ready => self::GOAL_SETTING_UP,
            default => self::GOAL_SET,
        };
    }

    public function goalStatusLabel(): string
    {
        return match ($this->goalStatus()) {
            self::GOAL_SET => 'Goal set',
            self::GOAL_SETTING_UP => 'Setting up',
            default => 'Not started',
        };
    }

    /**
     * The name on the scoreboard: the member's team name in this league, or their display name.
     */
    public function displayName(): string
    {
        return filled($this->team_name) ? $this->team_name : $this->user->name;
    }

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
