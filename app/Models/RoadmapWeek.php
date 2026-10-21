<?php

namespace App\Models;

use App\GoalWeekResult;
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
     * Days after a GoalWeek ends that check-ins stay open before its result locks.
     */
    public const CHECK_IN_GRACE_DAYS = 1;

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
            'milestone_hit_at' => 'datetime',
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
     * Counts the weeks in earlier stretches, so a short first week and longer stretches both number correctly.
     */
    public function label(): string
    {
        $earlierWeeks = $this->goal->weeks
            ->filter(fn (RoadmapWeek $week) => $week->position < $this->position)
            ->sum(fn (RoadmapWeek $week) => $week->lengthInWeeks());

        $first = $earlierWeeks + 1;
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

    /**
     * Whether the GoalWeek has begun.
     */
    public function isStarted(): bool
    {
        return $this->starts_on->lte(today());
    }

    /**
     * Whether the result is final: the grace day after the GoalWeek has passed.
     */
    public function isLocked(): bool
    {
        return $this->ends_on->copy()->addDays(self::CHECK_IN_GRACE_DAYS)->lt(today());
    }

    /**
     * Whether the person can still check off tasks and the milestone.
     */
    public function isCheckable(): bool
    {
        return $this->isStarted() && ! $this->isLocked() && $this->goal->isActive();
    }

    /**
     * Share of the planned (non-bonus) tasks that are done, from 0 to 1.
     */
    public function completionShare(): float
    {
        $planned = $this->tasks->reject->is_bonus;

        return $planned->isEmpty() ? 1.0 : $planned->filter->completed_at->count() / $planned->count();
    }

    /**
     * The GoalWeek's result so far, or its final result once locked. Null before it starts.
     */
    public function result(): ?GoalWeekResult
    {
        if (! $this->isStarted()) {
            return null;
        }

        $planned = $this->tasks->reject->is_bonus;

        return GoalWeekResult::score(
            planned: $planned->count(),
            plannedDone: $planned->filter->completed_at->count(),
            bonusDone: $this->tasks->filter->is_bonus->filter->completed_at->count(),
            milestoneHit: $this->milestone_hit_at !== null,
        );
    }

    /**
     * Whether to show "Not started" instead of a result: it's the GoalWeek's first day and nothing is checked off yet.
     * From the second day on, the real result shows. Scoring is unaffected.
     */
    public function isNotStarted(): bool
    {
        return $this->starts_on->isToday()
            && $this->milestone_hit_at === null
            && $this->tasks->every(fn (RoadmapTask $task) => $task->completed_at === null);
    }

    /**
     * Whether the result is In line but below the safe zone.
     */
    public function isCloseCall(): bool
    {
        return $this->result() === GoalWeekResult::InLine && $this->completionShare() < GoalWeekResult::SAFELY_IN_LINE_AT;
    }

    /**
     * How many more planned tasks would put the GoalWeek safely in line.
     */
    public function tasksToSafety(): int
    {
        $planned = $this->tasks->reject->is_bonus;
        $needed = (int) ceil($planned->count() * GoalWeekResult::SAFELY_IN_LINE_AT);

        return max(0, $needed - $planned->filter->completed_at->count());
    }
}
