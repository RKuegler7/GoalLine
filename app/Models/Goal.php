<?php

namespace App\Models;

use App\Clock;
use App\GoalCategory;
use App\GoalStatus;
use App\Jobs\GenerateRoadmap;
use App\Jobs\ReviewRoadmapChanges;
use App\PlanVerdict;
use App\RoadmapStatus;
use App\Services\StoredPhoto;
use Carbon\CarbonInterface;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'category', 'why', 'target_date', 'target_result', 'context'])]
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
     * Remove uploaded plans, proof photos, and the completion photo along with the goal. The weeks and tasks go with
     * the database's cascade, so their proof photos are deleted here.
     */
    protected static function booted(): void
    {
        static::deleting(function (Goal $goal) {
            $goal->materials()->get()->each->delete();

            $proofs = RoadmapTask::whereIn('roadmap_week_id', $goal->weeks()->select('id'))->whereNotNull('proof_path')->pluck('proof_path');
            Storage::disk(RoadmapTask::proofDisk())->delete($proofs->all());

            if ($goal->completion_photo_path) {
                Storage::disk(self::PHOTO_DISK)->delete($goal->completion_photo_path);
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
     * Active goals whose timeline hasn't ended: the ones with work this week or ahead. An active goal past its target
     * date (and its last week's check-in grace day) has no GoalWeeks left, so it belongs in History until it's
     * marked done, retired or given a new date.
     */
    public function scopeInPlay(Builder $query): void
    {
        $query->where('status', GoalStatus::Active)
            ->whereDate('target_date', '>=', Clock::today()->subDays(RoadmapWeek::CHECK_IN_GRACE_DAYS)->toDateString());
    }

    /**
     * Whether the goal is active but its timeline, grace day included, is over.
     */
    public function hasEndedTimeline(): bool
    {
        return $this->isActive() && $this->target_date->copy()->addDays(RoadmapWeek::CHECK_IN_GRACE_DAYS)->lt(Clock::today());
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
    public const PHOTO_DISK = 'uploads';

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
    public function complete(?string $note = null, ?string $photoPath = null, ?string $photoMimeType = null): void
    {
        $this->forceFill([
            'status' => GoalStatus::Completed,
            'completed_at' => now(),
            'completion_note' => $note,
            'completion_photo_path' => $photoPath,
            'completion_photo_mime_type' => $photoPath ? ($photoMimeType ?? StoredPhoto::typeFor($photoPath)) : null,
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
     * Build the first roadmap once the intake questions are answered or skipped. If an uploaded plan is still being
     * read, reading it builds the roadmap next.
     */
    public function startRoadmap(): void
    {
        if ($this->materials()->where('status', RoadmapStatus::Pending)->exists()) {
            $this->forceFill(['roadmap_status' => RoadmapStatus::Pending])->save();

            return;
        }

        $this->requestRoadmap();
    }

    /**
     * The first stretch that hasn't started yet, where a refresh can change the plan without touching this week.
     */
    public function upcomingPosition(): ?int
    {
        return $this->weeks->first(fn (RoadmapWeek $week) => $week->starts_on->gt(Clock::today()))?->position;
    }

    /**
     * Saved versions of the roadmap, newest last.
     *
     * @return HasMany<PlanVersion, $this>
     */
    public function planVersions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    /**
     * Proposed changes from "Refine your plan" and restores, waiting for a preview.
     *
     * @return HasMany<PlanRefinement, $this>
     */
    public function refinements(): HasMany
    {
        return $this->hasMany(PlanRefinement::class);
    }

    /**
     * The coach's intake questions and the person's answers.
     *
     * @return HasOne<GoalIntake, $this>
     */
    public function intake(): HasOne
    {
        return $this->hasOne(GoalIntake::class);
    }

    /**
     * Whether this is a physical goal, which gets a health question, a cautious plan, and a not-medical-advice note.
     */
    public function isPhysical(): bool
    {
        return $this->category === GoalCategory::Fitness
            || preg_match('/\b(run|running|marathon|5k|10k|race|triathlon|lift|lifting|squat|bench|deadlift|weight|swim|cycling|bike|hike|workout|gym|75 hard|steps)\b/i', $this->title) === 1;
    }

    /**
     * Note an edit the person made to their roadmap and queue a coach review.
     */
    public function recordRoadmapChange(string $description): void
    {
        PlanVersion::recordEdit($this, $description);

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
     * GoalLeagues this goal plays in.
     *
     * @return BelongsToMany<League, $this>
     */
    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_members')->orderBy('leagues.id');
    }

    /**
     * The GoalLeagues a check-in post from this goal can go to, looked up once per goal.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, League>
     */
    public function postingLeagues(): \Illuminate\Database\Eloquent\Collection
    {
        return once(fn () => $this->leagues()->get());
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

    /**
     * Every habit this goal has had, including retired ones (they still count in past GoalWeeks).
     *
     * @return HasMany<Habit, $this>
     */
    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class)->oldest('id');
    }

    /**
     * Habits still being practiced.
     *
     * @return Collection<int, Habit>
     */
    public function currentHabits(): Collection
    {
        return $this->habits->whereNull('archived_at')->values();
    }
}
