<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member questions another member's Challenge check-in. The Challenge owner decides: upheld takes the check-in off
 * (voided), dismissed keeps it. People decide, never the AI.
 */
#[Fillable(['disputer_id', 'reason'])]
class ChallengeDispute extends Model
{
    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    public const UPHELD = 'upheld';

    public const DISMISSED = 'dismissed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ChallengeCheckIn, $this>
     */
    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(ChallengeCheckIn::class, 'challenge_check_in_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disputer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disputer_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
