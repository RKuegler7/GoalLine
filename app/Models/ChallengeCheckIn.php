<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One member's check-in for one day of a Challenge: done (with the checklist ticked, or a number), slipped (Abstain),
 * or voided when an upheld dispute takes it off. An optional proof photo earns the Verified check when it's added
 * the same day it's for.
 */
#[Fillable(['user_id', 'checked_on', 'status', 'value', 'rules_done'])]
class ChallengeCheckIn extends Model
{
    public const DONE = 'done';

    public const SLIPPED = 'slipped';

    public const VOIDED = 'voided';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'value' => 'decimal:2',
            'rules_done' => 'array',
            'proof_uploaded_at' => 'datetime',
        ];
    }

    /**
     * Check-ins that count as activity elsewhere (streak, heatmap, recap): not slipped, not voided.
     *
     * @param  Builder<ChallengeCheckIn>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where('status', self::DONE);
    }

    /**
     * @return BelongsTo<Challenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ChallengeDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(ChallengeDispute::class);
    }

    /**
     * Verified: a proof photo added on the day the check-in is for (New York time).
     */
    public function isVerified(): bool
    {
        return $this->proof_path !== null
            && $this->proof_uploaded_at !== null
            && $this->proof_uploaded_at->copy()->setTimezone(Challenge::TIMEZONE)->toDateString() === $this->checked_on->toDateString();
    }
}
