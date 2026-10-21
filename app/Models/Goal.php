<?php

namespace App\Models;

use App\GoalCategory;
use App\GoalStatus;
use App\Jobs\GenerateRoadmap;
use App\RoadmapStatus;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'category', 'why', 'target_date'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    /**
     * Default values for new goals.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'roadmap_status' => 'none',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => GoalCategory::class,
            'status' => GoalStatus::class,
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'roadmap_status' => RoadmapStatus::class,
            'roadmap_generated_at' => 'datetime',
        ];
    }

    /**
     * The person pursuing this goal.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to goals still being pursued.
     *
     * @param  Builder<Goal>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', GoalStatus::Active);
    }

    /**
     * Whole days from today until the target date. Negative once the date has passed.
     */
    public function daysRemaining(): int
    {
        return (int) today()->diffInDays($this->target_date, false);
    }

    /**
     * The stretches of this goal's roadmap, in order.
     *
     * @return HasMany<RoadmapWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(RoadmapWeek::class)->orderBy('position');
    }

    /**
     * The roadmap stretch that includes today, if any.
     */
    public function currentWeek(): ?RoadmapWeek
    {
        return $this->weeks->first(fn (RoadmapWeek $week) => $week->isCurrent());
    }

    /**
     * Mark the roadmap as being built and queue the work.
     */
    public function requestRoadmap(): void
    {
        $this->forceFill(['roadmap_status' => RoadmapStatus::Pending])->save();

        GenerateRoadmap::dispatch($this);
    }

    /**
     * How many weeks each roadmap stretch covers: 1 for a weekly plan, more for longer goals.
     */
    public function weeksPerStretch(): int
    {
        $first = $this->weeks->first();

        return $first ? max(1, (int) round(($first->starts_on->diffInDays($first->ends_on) + 1) / 7)) : 1;
    }

    /**
     * What a roadmap stretch is called: a gameweek, or a multi-week match window.
     */
    public function stretchName(): string
    {
        return $this->weeksPerStretch() > 1 ? 'Match window' : 'Gameweek';
    }
}
