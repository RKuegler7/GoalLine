<?php

namespace App\Models;

use App\GoalOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A Goal Day check-in: what someone says happened. Self-reported, so it never changes stars or standings.
 */
#[Fillable(['user_id', 'league_id', 'outcome', 'finished_early', 'actual_result', 'hardest_part', 'helped_most', 'shared', 'answered_at', 'recommendations'])]
class GoalResult extends Model
{
    /**
     * How long after answering the check-in can still be changed.
     */
    public const EDITABLE_DAYS = 7;

    protected static function booted(): void
    {
        static::deleting(function (GoalResult $result) {
            if ($result->proof_path) {
                Storage::disk(RoadmapTask::proofDisk())->delete($result->proof_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => GoalOutcome::class,
            'finished_early' => 'boolean',
            'shared' => 'boolean',
            'verified_at' => 'datetime',
            'answered_at' => 'datetime',
            'recommendations' => 'array',
            'public_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * The league mate who confirmed it, if anyone did.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isAchieved(): bool
    {
        return $this->outcome === GoalOutcome::Achieved;
    }

    /**
     * Verified by proof or by a league mate's confirm.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Whether the person can still change their answer.
     */
    public function isEditable(): bool
    {
        return $this->answered_at->copy()->addDays(self::EDITABLE_DAYS)->isFuture();
    }

    /**
     * Whether another league member can confirm this result with one tap.
     */
    public function canBeConfirmedBy(User $viewer): bool
    {
        return $this->isAchieved()
            && ! $this->isVerified()
            && $this->shared
            && $this->league_id !== null
            && $viewer->id !== $this->user_id
            && LeagueMember::where('league_id', $this->league_id)->where('user_id', $viewer->id)->exists();
    }

    public function proofUrl(): ?string
    {
        return $this->proof_path ? route('goal-day.proof', $this->goal_id) : null;
    }
}
