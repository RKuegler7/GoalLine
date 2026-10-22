<?php

namespace App\Models;

use App\RoadmapStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's reflection at the end of a GoalWeek, and the coach's tip for the week ahead.
 */
#[Fillable(['feeling', 'went_well', 'in_the_way'])]
class WeekReview extends Model
{
    /**
     * How the GoalWeek felt, from 1 to 5.
     *
     * @var array<int, string>
     */
    public const FEELINGS = [1 => 'Rough', 2 => 'Meh', 3 => 'Solid', 4 => 'Strong', 5 => 'On fire'];

    public const MAX_LENGTH = 1000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feeling' => 'integer',
            'coach_status' => RoadmapStatus::class,
        ];
    }

    /**
     * @return BelongsTo<RoadmapWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(RoadmapWeek::class, 'roadmap_week_id');
    }

    public function feelingLabel(): ?string
    {
        return self::FEELINGS[$this->feeling] ?? null;
    }
}
