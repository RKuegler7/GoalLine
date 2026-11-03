<?php

namespace App\Models;

use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A matchup opponent or league owner questioning a check-in. It's resolved by proof, the owner's explanation, the
 * disputer withdrawing it, or a ruling from the league owner. Until then, the check-in's points are held out once
 * its GoalWeek closes.
 */
#[Fillable(['disputer_id', 'league_id', 'reason'])]
class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory;

    public const OPEN = 'open';

    /**
     * Settled: the check-in counts.
     */
    public const RESOLVED = 'resolved';

    /**
     * The disputer took it back: the check-in counts.
     */
    public const WITHDRAWN = 'withdrawn';

    /**
     * The league owner ruled it doesn't count.
     */
    public const UPHELD = 'upheld';

    /**
     * Open disputes one person can have per matchup per GoalWeek.
     */
    public const MAX_OPEN_PER_MATCHUP = 3;

    public const MAX_REASON = 200;

    public const MAX_NOTE = 300;

    /**
     * Default values for new disputes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::OPEN,
    ];

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
     * @return BelongsTo<RoadmapTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(RoadmapTask::class, 'roadmap_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disputer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disputer_id');
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
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /**
     * "Settled with proof", "Withdrawn", and so on.
     */
    public function outcomeLabel(): string
    {
        return match ($this->resolution) {
            'proof' => 'Settled with proof',
            'note' => 'Settled with an explanation',
            'withdrawn' => 'Withdrawn',
            'ruled_counts' => 'League owner ruled it counts',
            'ruled_out' => 'League owner ruled it doesn\'t count',
            default => 'Open',
        };
    }
}
