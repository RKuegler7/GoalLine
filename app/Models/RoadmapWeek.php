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
}
