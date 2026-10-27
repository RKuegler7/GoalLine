<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A league mate asked to see the work behind a check-in. It never changes a score by itself.
 */
#[Fillable(['requester_id'])]
class ProofRequest extends Model
{
    /**
     * @return BelongsTo<RoadmapTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(RoadmapTask::class, 'roadmap_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
