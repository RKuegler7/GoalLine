<?php

namespace App\Models;

use App\Clock;
use Carbon\CarbonInterface;
use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring action that drives a goal, like "Run" four times a week.
 * Its weekly target counts toward the GoalWeek result alongside planned tasks.
 */
#[Fillable(['title', 'times_per_week', 'days'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'times_per_week' => 'integer',
            'days' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The goal this habit serves.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Each day the habit was done.
     *
     * @return HasMany<HabitLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    /**
     * Habits still being practiced.
     *
     * @param  Builder<Habit>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Whether the habit counted during a GoalWeek: created before it ended and not retired before it began.
     * Retiring a habit never changes results for weeks it already counted in.
     */
    public function countsIn(RoadmapWeek $week): bool
    {
        return $this->created_at->startOfDay()->lte($week->ends_on)
            && ($this->archived_at === null || $this->archived_at->gt($week->starts_on));
    }

    /**
     * How many times the habit is expected during a GoalWeek, in proportion to its length: fewer in a
     * short first week, more in a stretch that spans several weeks. Never more than once a day.
     */
    public function targetFor(RoadmapWeek $week): int
    {
        $days = (int) $week->starts_on->diffInDays($week->ends_on) + 1;

        return max(1, min($days, (int) ceil($this->times_per_week * $days / 7)));
    }

    /**
     * How many days the habit was done within a GoalWeek.
     */
    public function doneIn(RoadmapWeek $week): int
    {
        return $this->logs->filter(fn (HabitLog $log) => $log->logged_on->between($week->starts_on, $week->ends_on))->count();
    }

    /**
     * Whether the habit was done on a given day.
     */
    public function isDoneOn(CarbonInterface $day): bool
    {
        return $this->logs->contains(fn (HabitLog $log) => $log->logged_on->isSameDay($day));
    }

    /**
     * Whether the habit belongs on today's gameplan: one of its preferred days, or any day while
     * this week's target isn't met yet (or it's already been done today).
     */
    public function isDueToday(RoadmapWeek $week): bool
    {
        $today = Clock::today();

        if ($this->isDoneOn($today)) {
            return true;
        }

        if ($this->days) {
            return in_array($today->dayOfWeekIso, $this->days, true);
        }

        return $this->doneIn($week) < $this->targetFor($week);
    }

    /**
     * "4× a week" or "Daily", with preferred days when set: "3× a week · Mon, Wed, Fri".
     */
    public function cadence(): string
    {
        $base = $this->times_per_week >= 7 ? 'Daily' : $this->times_per_week.'× a week';

        if (! $this->days || $this->times_per_week >= 7) {
            return $base;
        }

        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return $base.' · '.collect($this->days)->sort()->map(fn (int $day) => $names[$day])->implode(', ');
    }
}
