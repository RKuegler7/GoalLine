<?php

namespace App\Models;

use Database\Factories\PlanRefinementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposed change to a goal's upcoming GoalWeeks, from the coach ("Refine your plan") or from an earlier version
 * (restore). Nothing changes until the person previews it and taps Accept.
 */
#[Fillable(['kind', 'instruction', 'plan_version_id'])]
class PlanRefinement extends Model
{
    /** @use HasFactory<PlanRefinementFactory> */
    use HasFactory;

    public const REFINE = 'refine';

    public const RESTORE = 'restore';

    public const PENDING = 'pending';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const ACCEPTED = 'accepted';

    public const DISCARDED = 'discarded';

    /**
     * Longest instruction, in characters.
     */
    public const MAX_INSTRUCTION = 500;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposal' => 'array',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<PlanVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    /**
     * Whether it still waits for the coach or for the person's decision.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::READY], true);
    }

    /**
     * Close it with a final status.
     */
    public function decide(string $status): void
    {
        $this->forceFill(['status' => $status, 'decided_at' => now()])->save();
    }
}
