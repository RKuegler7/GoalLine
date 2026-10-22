<?php

namespace App\Models;

use App\Clock;
use App\GoalCategory;
use App\GoalStatus;
use App\Jobs\GenerateRoadmap;
use App\Jobs\ReviewRoadmapChanges;
use App\PlanVerdict;
use App\RoadmapStatus;
use Carbon\CarbonInterface;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['title', 'category', 'why', 'target_date'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    /**
     * How long after setting a goal it can still be removed outright.
     */
    public const TRIAL_DAYS = 7;

    /**
     * Seconds to wait after an edit before the coach reviews, so a batch of edits gets one review.
     */
    public const REVIEW_DELAY_SECONDS = 20;

    /**
     * Default values for new goals.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'roadmap_status' => 'none',
        'plan_check_status' => 'none',
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
            'retired_at' => 'datetime',
            'roadmap_status' => RoadmapStatus::class,
            'roadmap_generated_at' => 'datetime',
            'pending_changes' => 'array',
            'plan_check_status' => RoadmapStatus::class,
            'plan_verdict' => PlanVerdict::class,
            'plan_checked_at' => 'datetime',
            'plan_fix' => 'array',
            'plan_dismissed_at' => 'datetime',
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
     * Whether the goal is still being pursued.
     */
    public function isActive(): bool
    {
        return $this->status === GoalStatus::Active;
    }

    /**
     * When the trial ends and the person is locked in.
     */
    public function lockedInAt(): CarbonInterface
    {
        return ($this->created_at ?? now())->copy()->addDays(self::TRIAL_DAYS);
    }

    /**
     * Whether the goal is still in its trial and can be removed outright.
     */
    public function inTrial(): bool
    {
        return $this->lockedInAt()->isFuture();
    }

    /**
     * Completion photos are kept on the private disk and served through a route.
     */
    public const PHOTO_DISK = 'local';

    /**
     * Whether the person crossed the line on this goal.
     */
    public function isCompleted(): bool
    {
        return $this->status === GoalStatus::Completed;
    }

    /**
     * Mark the goal as achieved, with an optional reflection and photo.
     */
    public function complete(?string $note = null, ?string $photoPath = null): void
    {
        $this->forceFill([
            'status' => GoalStatus::Completed,
            'completed_at' => now(),
            'completion_note' => $note,
            'completion_photo_path' => $photoPath,
        ])->save();
    }

    /**
     * Where the completion photo is served from, if there is one.
     */
    public function completionPhotoUrl(): ?string
    {
        return $this->completion_photo_path ? route('goals.photo', $this) : null;
    }

    /**
     * Stop pursuing the goal while keeping its history.
     */
    public function retire(): void
    {
        $this->forceFill(['status' => GoalStatus::Retired, 'retired_at' => now()])->save();
    }

    /**
     * Whole days from today until the target date. Negative once the date has passed.
     */
    public function daysRemaining(): int
    {
        return (int) Clock::today()->diffInDays($this->target_date, false);
    }

    /**
     * The stretches of this goal's roadmap, in order.
     *
     * @return HasMany<RoadmapWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(RoadmapWeek::class)->orderBy('position')->chaperone();
    }

    /**
     * The roadmap stretch that includes today, if any.
     */
    public function currentWeek(): ?RoadmapWeek
    {
        return $this->weeks->first(fn (RoadmapWeek $week) => $week->isCurrent());
    }

    /**
     * Where a "re-plan from here" starts: the current stretch, or the next one if none is current.
     */
    public function replanFromPosition(): ?int
    {
        return $this->weeks->first(fn (RoadmapWeek $week) => ! $week->isPast())?->position;
    }

    /**
     * Mark the roadmap as being built and queue the work.
     * Pass a position to keep earlier stretches and re-plan only from there.
     */
    public function requestRoadmap(?int $fromPosition = null): void
    {
        $this->forceFill(['roadmap_status' => RoadmapStatus::Pending])->save();

        GenerateRoadmap::dispatch($this, $fromPosition);
    }

    /**
     * Note an edit the person made to their roadmap and queue a coach review.
     */
    public function recordRoadmapChange(string $description): void
    {
        $this->forceFill([
            'pending_changes' => [...($this->pending_changes ?? []), $description],
            'plan_check_status' => RoadmapStatus::Pending,
        ])->save();

        ReviewRoadmapChanges::dispatch($this)->delay(now()->addSeconds(self::REVIEW_DELAY_SECONDS));
    }

    /**
     * How many weeks each roadmap stretch covers: 1 for a weekly plan, more for longer goals.
     */
    public function weeksPerStretch(): int
    {
        return $this->weeks->map(fn (RoadmapWeek $week) => $week->lengthInWeeks())->max() ?? 1;
    }

    /**
     * Plans, programs, and courses the person uploaded to shape this goal's roadmap.
     *
     * @return HasMany<PlanMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(PlanMaterial::class)->oldest();
    }

    /**
     * Materials Claude has finished reading.
     *
     * @return Collection<int, PlanMaterial>
     */
    public function readMaterials(): Collection
    {
        return $this->materials->filter(fn (PlanMaterial $material) => $material->status === RoadmapStatus::Ready && $material->digest)->values();
    }

    /**
     * GoalWeek result points earned so far, including the current GoalWeek's live result.
     */
    public function points(): int
    {
        return $this->weeks->sum(fn (RoadmapWeek $week) => $week->result()?->points() ?? 0);
    }
}
