<?php

namespace App\Models;

use Database\Factories\GroupTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task a GoalLeague member proposes for everyone's GoalWeek. It joins every member's plan once two thirds of the
 * league vote yes.
 */
#[Fillable(['proposer_id', 'title', 'description', 'points', 'week', 'closes_at'])]
class GroupTask extends Model
{
    /** @use HasFactory<GroupTaskFactory> */
    use HasFactory;

    public const VOTING = 'voting';

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const MIN_POINTS = 1;

    public const MAX_POINTS = 10;

    public const DEFAULT_POINTS = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'week' => 'integer',
            'closes_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Yes votes needed: two thirds of the league, rounded up. 2 members need 2, 3 need 2, 4 need 3, 10 need 7.
     */
    public static function requiredYes(int $members): int
    {
        return intdiv(2 * $members + 2, 3);
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    /**
     * @return HasMany<GroupTaskVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(GroupTaskVote::class);
    }

    /**
     * The copies added to each member's GoalWeek once it passed.
     *
     * @return HasMany<RoadmapTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(RoadmapTask::class);
    }

    public function isVoting(): bool
    {
        return $this->status === self::VOTING;
    }

    public function yesCount(): int
    {
        return $this->votes->where('approve', true)->count();
    }

    public function noCount(): int
    {
        return $this->votes->where('approve', false)->count();
    }

    /**
     * This person's vote: true, false, or null if they haven't voted.
     */
    public function voteOf(User $user): ?bool
    {
        return $this->votes->firstWhere('user_id', $user->id)?->approve;
    }
}
