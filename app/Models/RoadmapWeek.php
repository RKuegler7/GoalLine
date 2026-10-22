<?php

namespace App\Models;

use App\Clock;
use App\GoalWeekResult;
use Carbon\CarbonInterface;
use Database\Factories\RoadmapWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        return Clock::today()->between($this->starts_on, $this->ends_on);
    }

    /**
     * Whether this stretch has already ended.
     */
    public function isPast(): bool
    {
        return $this->ends_on->lt(Clock::today());
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
        return $this->starts_on->lte(Clock::today());
    }

    /**
     * Whether the result is final: the grace day after the GoalWeek has passed.
     */
    public function isLocked(): bool
    {
        return $this->ends_on->copy()->addDays(self::CHECK_IN_GRACE_DAYS)->lt(Clock::today());
    }

    /**
     * Whether the person can still check off tasks and the milestone.
     */
    public function isCheckable(): bool
    {
        return $this->isStarted() && ! $this->isLocked() && $this->goal->isActive();
    }

    /**
     * Habits that count in this GoalWeek.
     *
     * @return Collection<int, Habit>
     */
    public function habits(): Collection
    {
        return $this->goal->habits->filter(fn (Habit $habit) => $habit->countsIn($this))->values();
    }

    /**
     * Planned work this GoalWeek: planned (non-bonus) tasks plus each habit's weekly target.
     */
    public function plannedUnits(): int
    {
        return $this->tasks->reject->is_bonus->count()
            + $this->habits()->sum(fn (Habit $habit) => $habit->targetFor($this));
    }

    /**
     * Planned work done: planned tasks checked off plus habit check-ins, up to each habit's target.
     */
    public function doneUnits(): int
    {
        return $this->tasks->reject->is_bonus->filter->completed_at->count()
            + $this->habits()->sum(fn (Habit $habit) => min($habit->targetFor($this), $habit->doneIn($this)));
    }

    /**
     * Share of the planned work that's done, from 0 to 1.
     */
    public function completionShare(): float
    {
        $planned = $this->plannedUnits();

        return $planned === 0 ? 1.0 : $this->doneUnits() / $planned;
    }

    /**
     * The GoalWeek's result so far, or its final result once locked. Null before it starts.
     */
    public function result(): ?GoalWeekResult
    {
        if (! $this->isStarted()) {
            return null;
        }

        return GoalWeekResult::score(
            planned: $this->plannedUnits(),
            plannedDone: $this->doneUnits(),
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
        return $this->starts_on->isSameDay(Clock::today())
            && $this->milestone_hit_at === null
            && $this->doneUnits() === 0;
    }

    /**
     * Whether the result is In line but below the safe zone.
     */
    public function isCloseCall(): bool
    {
        return $this->result() === GoalWeekResult::InLine && $this->completionShare() < GoalWeekResult::SAFELY_IN_LINE_AT;
    }

    /**
     * How many more check-ins (tasks or habit days) would put the GoalWeek safely in line.
     */
    public function tasksToSafety(): int
    {
        return max(0, (int) ceil($this->plannedUnits() * GoalWeekResult::SAFELY_IN_LINE_AT) - $this->doneUnits());
    }

    /**
     * Tasks planned for a given day.
     *
     * @return Collection<int, RoadmapTask>
     */
    public function tasksOn(CarbonInterface $day): Collection
    {
        return $this->tasks->filter(fn (RoadmapTask $task) => $task->planned_on?->isSameDay($day))->values();
    }
}
