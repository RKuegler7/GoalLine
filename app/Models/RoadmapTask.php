<?php

namespace App\Models;

use Database\Factories\RoadmapTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['position', 'description', 'is_bonus', 'planned_on'])]
class RoadmapTask extends Model
{
    /** @use HasFactory<RoadmapTaskFactory> */
    use HasFactory;

    /**
     * A numeric result above this multiple of the plan gets an "Are you sure?" before it's saved.
     */
    public const UNUSUALLY_HIGH_MULTIPLE = 1.5;

    /**
     * The highest result allowed, as a multiple of the plan, unless the range says otherwise.
     */
    public const DEFAULT_MAX_MULTIPLE = 3;

    /**
     * A check-in with a dispute waiting to be resolved.
     */
    public const DISPUTE_OPEN = 'open';

    /**
     * A check-in the league owner ruled doesn't count.
     */
    public const DISPUTE_UPHELD = 'upheld';

    /**
     * Remove the proof photo along with the task.
     */
    protected static function booted(): void
    {
        static::deleting(function (RoadmapTask $task) {
            if ($task->proof_path) {
                Storage::disk(self::proofDisk())->delete($task->proof_path);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'is_bonus' => 'boolean',
            'is_personal' => 'boolean',
            'is_floor' => 'boolean',
            'planned_on' => 'date',
            'points' => 'integer',
            'result_required' => 'boolean',
            'result_target' => 'float',
            'result_min' => 'float',
            'result_max' => 'float',
            'result_value' => 'float',
            'proof_uploaded_at' => 'datetime',
        ];
    }

    /**
     * The roadmap stretch this task belongs to.
     *
     * @return BelongsTo<RoadmapWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(RoadmapWeek::class, 'roadmap_week_id');
    }

    /**
     * League mates who asked to see the work behind this check-in.
     *
     * @return HasMany<ProofRequest, $this>
     */
    public function proofRequests(): HasMany
    {
        return $this->hasMany(ProofRequest::class);
    }

    /**
     * Disputes raised about this check-in by the matchup opponent or the league owner.
     *
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * The GoalLeague group task this copy came from, if it's a group task.
     *
     * @return BelongsTo<GroupTask, $this>
     */
    public function groupTask(): BelongsTo
    {
        return $this->belongsTo(GroupTask::class);
    }

    /**
     * Whether it's a group task the GoalLeague voted in.
     */
    public function isGroupTask(): bool
    {
        return $this->group_task_id !== null;
    }

    /**
     * The check-in post made from this task's photo, if it was shared.
     *
     * @return HasOne<Highlight, $this>
     */
    public function post(): HasOne
    {
        return $this->hasOne(Highlight::class, 'roadmap_task_id');
    }

    /**
     * The storage disk for proof photos: set PROOF_DISK to move them (to S3, say) with no code changes.
     */
    public static function proofDisk(): string
    {
        return (string) config('filesystems.proof_disk', 'uploads');
    }

    /**
     * Points this task has earned: its points once checked off, scaled by the result for a numeric task,
     * so running 7 of a planned 5 miles earns 1.4 times the points. A check-in the league owner ruled doesn't count
     * earns nothing, and one still disputed when its GoalWeek closes is held out until the dispute is resolved.
     */
    public function earnedPoints(bool $ignoreDisputes = false): float
    {
        if (! $this->completed_at) {
            return 0.0;
        }

        if (! $ignoreDisputes && ($this->dispute_status === self::DISPUTE_UPHELD
            || ($this->dispute_status === self::DISPUTE_OPEN && $this->week->isLocked()))) {
            return 0.0;
        }

        if ($this->result_required && $this->result_target > 0 && $this->result_value !== null) {
            return $this->points * $this->result_value / $this->result_target;
        }

        return (float) $this->points;
    }

    /**
     * Track a number for this task: the plan, its unit, and the allowed range (0 to three times the plan).
     */
    public function trackNumber(float $target, string $unit): void
    {
        $this->forceFill([
            'result_required' => true,
            'result_target' => $target,
            'result_unit' => trim($unit),
            'result_min' => 0,
            'result_max' => $target * self::DEFAULT_MAX_MULTIPLE,
        ]);
    }

    /**
     * Stop tracking a number for this task.
     */
    public function stopTrackingNumber(): void
    {
        $this->forceFill(['result_required' => false, 'result_target' => null, 'result_unit' => null, 'result_min' => null, 'result_max' => null, 'result_value' => null]);
    }

    /**
     * Whether a check-in has a photo behind it.
     */
    public function isVerified(): bool
    {
        return $this->proof_path !== null;
    }

    /**
     * "5 miles" for the plan, or "7.5 miles" for a result.
     */
    public function formatNumber(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        $number = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return trim($number.' '.$this->result_unit);
    }
}
