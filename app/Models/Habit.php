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
 * A recurring action that drives a goal, with an objective rule for what counts: "Run 2+ miles" on Mon, Wed, Fri,
 * or "Study 45+ minutes" 4 times a week. Its weekly target counts toward the GoalWeek result alongside planned tasks.
 */
#[Fillable(['title', 'times_per_week', 'cadence', 'days', 'rule', 'threshold', 'unit'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    /**
     * Every day.
     */
    public const DAILY = 'daily';

    /**
     * Specific days of the week.
     */
    public const DAYS = 'days';

    /**
     * Any X days a week; progress counts toward the target, and a day off isn't a miss.
     */
    public const WEEKLY = 'weekly';

    /**
     * Did it or not.
     */
    public const CHECK = 'check';

    /**
     * A number with a threshold, like 2+ miles or 25+ questions.
     */
    public const NUMBER = 'number';

    /**
     * Minutes with a threshold, like 20+ minutes.
     */
    public const MINUTES = 'minutes';

    public const DAY_NAMES = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /**
     * Default values for new habits.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cadence' => self::WEEKLY,
        'rule' => self::CHECK,
    ];

    /**
     * Seven times a week is every day, whoever set it.
     */
    protected static function booted(): void
    {
        static::saving(function (Habit $habit) {
            if ($habit->times_per_week >= 7) {
                $habit->cadence = self::DAILY;
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
            'times_per_week' => 'integer',
            'days' => 'array',
            'threshold' => 'float',
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
     * Each day the habit was logged.
     *
     * @return HasMany<HabitLog, $this>
     */
    public function logs(): HasMany
    {
        // Voided logs (made before kickoff) stay in the table for history but never count.
        return $this->hasMany(HabitLog::class)->whereNull('voided_at');
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
     * How many days within a GoalWeek met the habit's rule.
     */
    public function doneIn(RoadmapWeek $week): int
    {
        return $this->logs->filter(fn (HabitLog $log) => $log->met && $log->logged_on->between($week->starts_on, $week->ends_on))->count();
    }

    /**
     * The day's log, met or not.
     */
    public function logOn(CarbonInterface $day): ?HabitLog
    {
        return $this->logs->first(fn (HabitLog $log) => $log->logged_on->isSameDay($day));
    }

    /**
     * Whether the habit was done on a given day, meeting its rule.
     */
    public function isDoneOn(CarbonInterface $day): bool
    {
        return (bool) $this->logOn($day)?->met;
    }

    /**
     * Whether a number was logged that didn't reach the threshold yet.
     */
    public function isPartialOn(CarbonInterface $day): bool
    {
        $log = $this->logOn($day);

        return $log !== null && ! $log->met;
    }

    /**
     * Whether a day is a rest day: a specific-days habit on a day it isn't scheduled.
     */
    public function isRestDay(CarbonInterface $day): bool
    {
        return $this->cadence === self::DAYS && $this->days && ! in_array($day->dayOfWeekIso, $this->days, true);
    }

    /**
     * Whether the habit belongs on today's list: every day, its scheduled days, or any day while this week's target
     * isn't met yet. A habit already logged today stays on the list.
     */
    public function isDueToday(RoadmapWeek $week): bool
    {
        $today = Clock::today();

        if ($this->logOn($today)) {
            return true;
        }

        return match ($this->cadence) {
            self::DAILY => true,
            self::DAYS => ! $this->isRestDay($today),
            default => $this->days
                ? in_array($today->dayOfWeekIso, $this->days, true)
                : $this->doneIn($week) < $this->targetFor($week),
        };
    }

    /**
     * Whether a logged number meets the rule. A plain check-off always does.
     */
    public function meets(?float $value): bool
    {
        return $this->rule === self::CHECK || ($value !== null && $value >= (float) $this->threshold);
    }

    /**
     * Whether logging takes a number.
     */
    public function takesNumber(): bool
    {
        return $this->rule !== self::CHECK;
    }

    /**
     * The number's unit: "miles", or "minutes" for a minutes habit.
     */
    public function unitLabel(): string
    {
        return $this->rule === self::MINUTES ? 'minutes' : (string) $this->unit;
    }

    /**
     * What counts, in a few words: "2+ miles", "20+ minutes", or null for a plain check-off.
     */
    public function ruleLabel(): ?string
    {
        if (! $this->takesNumber()) {
            return null;
        }

        return trim($this->formatNumber((float) $this->threshold).'+ '.$this->unitLabel());
    }

    /**
     * The habit with its rule: "Run · 2+ miles".
     */
    public function label(): string
    {
        return $this->ruleLabel() ? $this->title.' · '.$this->ruleLabel() : $this->title;
    }

    /**
     * "2" rather than "2.00", "2.5" rather than "2.50".
     */
    public function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * "Every day", "Mon, Wed, Fri", or "4× a week" (with preferred days, if any, from before cadences were explicit).
     */
    public function cadence(): string
    {
        $names = fn (array $days) => collect($days)->sort()->map(fn (int $day) => self::DAY_NAMES[$day])->implode(', ');

        return match (true) {
            $this->cadence === self::DAILY || $this->times_per_week >= 7 => 'Every day',
            $this->cadence === self::DAYS && (bool) $this->days => $names($this->days),
            (bool) $this->days => $this->times_per_week.'× a week · '.$names($this->days),
            default => $this->times_per_week.'× a week',
        };
    }
}
