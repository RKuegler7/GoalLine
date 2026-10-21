<?php

namespace App\Models;

use Database\Factories\RoadmapWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['position', 'starts_on', 'ends_on', 'focus', 'milestone'])]
class RoadmapWeek extends Model
{
    /** @use HasFactory<RoadmapWeekFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * The goal this stretch of the roadmap belongs to.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * The tasks to complete during this stretch, in order.
     *
     * @return HasMany<RoadmapTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(RoadmapTask::class)->orderBy('position');
    }

    /**
     * Whether today falls within this stretch.
     */
    public function isCurrent(): bool
    {
        return today()->between($this->starts_on, $this->ends_on);
    }

    /**
     * Whether this stretch has already ended.
     */
    public function isPast(): bool
    {
        return $this->ends_on->lt(today());
    }

    /**
     * How many weeks this stretch covers, rounded, and at least one.
     */
    public function lengthInWeeks(): int
    {
        return max(1, (int) round(($this->starts_on->diffInDays($this->ends_on) + 1) / 7));
    }

    /**
     * The stretch's name: "GoalWeek 3", or "GoalWeeks 3–4" when it spans several weeks.
     */
    public function label(int $weeksPerStretch): string
    {
        $first = ($this->position - 1) * $weeksPerStretch + 1;
        $last = $first + $this->lengthInWeeks() - 1;

        return $first === $last ? "GoalWeek {$first}" : "GoalWeeks {$first}–{$last}";
    }

    /**
     * Whether the person can still change this stretch.
     */
    public function isEditable(): bool
    {
        return ! $this->isPast() && $this->goal->isActive();
    }
}
