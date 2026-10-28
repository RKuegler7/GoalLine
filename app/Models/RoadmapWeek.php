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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
     * The review opens this many days before a GoalWeek ends: Saturday for a Monday-to-Sunday week.
     */
    public const REVIEW_OPENS_DAYS_BEFORE_END = 1;

    /**
     * What hitting the GoalWeek's milestone is worth, in planned points.
     */
    public const MILESTONE_POINTS = 2;

    /**
     * Personal tasks: at most this many a GoalWeek, each worth 1 to PERSONAL_TASK_MAX_POINTS, together no more than
     * PERSONAL_SHARE_PERCENT of the AI-planned points.
     */
    public const PERSONAL_TASK_LIMIT = 3;

    public const PERSONAL_TASK_MAX_POINTS = 5;

    public const PERSONAL_SHARE_PERCENT = 15;

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
            'smashed_confirmed_at' => 'datetime',
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
        return $this->hasMany(RoadmapTask::class)->orderBy('position')->chaperone('week');
    }

    /**
     * The end-of-week reflection and coach's tip, once written.
     *
     * @return HasOne<WeekReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(WeekReview::class);
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
     * Personal tasks can be added before the GoalWeek starts and until Tuesday 11:59 p.m. of it, so nobody pads a
     * plan after the fact.
     */
    public function acceptsPersonalTasks(): bool
    {
        return $this->isEditable() && Clock::today()->lte($this->starts_on->copy()->addDay());
    }

    /**
     * Points the AI coach planned: its tasks and the milestone, not personal, group, or bonus tasks.
     */
    public function aiPlannedPoints(): int
    {
        return $this->tasks->reject(fn (RoadmapTask $task) => $task->is_bonus || $task->is_personal || $task->isGroupTask())->sum('points')
            + self::MILESTONE_POINTS;
    }

    /**
     * Personal points still allowed this GoalWeek: 15% of the AI-planned points, rounded down, minus what's added.
     */
    public function personalPointsLeft(): int
    {
        $used = $this->tasks->where('is_personal', true)->sum('points');

        return max(0, intdiv($this->aiPlannedPoints() * self::PERSONAL_SHARE_PERCENT, 100) - $used);
    }

    /**
     * Personal tasks still allowed this GoalWeek.
     */
    public function personalTasksLeft(): int
    {
        return max(0, self::PERSONAL_TASK_LIMIT - $this->tasks->where('is_personal', true)->count());
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
     * Whether it's time for the end-of-week review: from just before the GoalWeek ends until check-ins close.
     */
    public function isReviewable(): bool
    {
        return $this->isCheckable() && Clock::today()->gte($this->ends_on->copy()->subDays(self::REVIEW_OPENS_DAYS_BEFORE_END));
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
     * Planned points this GoalWeek: planned (non-bonus) tasks' points, one point per planned habit day, and the
     * milestone.
     */
    public function plannedPoints(): int
    {
        return $this->tasks->reject->is_bonus->sum('points')
            + $this->habits()->sum(fn (Habit $habit) => $habit->targetFor($this))
            + self::MILESTONE_POINTS;
    }

    /**
     * Points earned: every task checked off (bonus tasks too, and numeric tasks scaled by their result), every habit
     * day logged, and the milestone. Earning more than planned is how a week gets past the line.
     */
    public function earnedPoints(): float
    {
        return $this->tasks->sum(fn (RoadmapTask $task) => $task->earnedPoints())
            + $this->habits()->sum(fn (Habit $habit) => $habit->doneIn($this))
            + ($this->milestone_hit_at ? self::MILESTONE_POINTS : 0);
    }

    /**
     * Share of the planned points earned: 1.0 is exactly the plan, and it can go well above.
     */
    public function completionShare(): float
    {
        $planned = $this->plannedPoints();

        return $planned === 0 ? 1.0 : $this->earnedPoints() / $planned;
    }

    /**
     * Whether a Smashed week can count as Smashed: at least one check-in has proof, or the matchup opponent confirmed it.
     */
    public function isSmashVerified(): bool
    {
        return $this->smashed_confirmed_at !== null
            || $this->tasks->contains(fn (RoadmapTask $task) => $task->completed_at && $task->isVerified());
    }

    /**
     * Whether the week reached Smashed but is credited as Past the line until there's proof or the opponent's OK.
     */
    public function needsProofForSmashed(): bool
    {
        return $this->isStarted()
            && GoalWeekResult::reachesSmashed($this->completionShare())
            && ! $this->isSmashVerified();
    }

    /**
     * The GoalWeek's result so far, or its final result once locked. Null before it starts.
     */
    public function result(): ?GoalWeekResult
    {
        if (! $this->isStarted()) {
            return null;
        }

        return GoalWeekResult::score($this->completionShare(), $this->isSmashVerified());
    }

    /**
     * Whether to show "Not started" instead of a result: it's the GoalWeek's first day and nothing is checked off yet.
     * From the second day on, the real result shows. Scoring is unaffected.
     */
    public function isNotStarted(): bool
    {
        return $this->starts_on->isSameDay(Clock::today())
            && $this->earnedPoints() == 0;
    }

    /**
     * Whether the result is In line but below the safe zone.
     */
    public function isCloseCall(): bool
    {
        return $this->result() === GoalWeekResult::InLine && round($this->completionShare(), 4) < GoalWeekResult::SAFELY_IN_LINE_AT;
    }

    /**
     * How many more points would put the GoalWeek safely in line.
     */
    public function pointsToSafety(): int
    {
        return max(0, (int) ceil($this->plannedPoints() * GoalWeekResult::SAFELY_IN_LINE_AT - $this->earnedPoints() - 0.0001));
    }

    /**
     * How many more points would put the GoalWeek in line, past the line, or into Smashed territory.
     */
    public function pointsTo(float $share): int
    {
        return max(0, (int) ceil($this->plannedPoints() * $share - $this->earnedPoints() - 0.0001));
    }

    /**
     * Who confirmed this week's Smashed result.
     *
     * @return BelongsTo<User, $this>
     */
    public function smashedConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'smashed_confirmed_by');
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
