<?php

namespace App\Models;

use Database\Factories\RoadmapTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'description'])]
class RoadmapTask extends Model
{
    /** @use HasFactory<RoadmapTaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The roadmap stretch this task belongs to.
     *
     * @return BelongsTo<RoadmapWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(RoadmapWeek::class, 'roadmap_week_id');
    }
}
