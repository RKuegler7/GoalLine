<?php

namespace App\Models;

use App\Services\PlanEditor;
use Database\Factories\PlanVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One saved version of a goal's roadmap, with what changed. Restoring one brings its upcoming GoalWeeks back.
 */
class PlanVersion extends Model
{
    /** @use HasFactory<PlanVersionFactory> */
    use HasFactory;

    public const BUILT = 'built';

    public const REFINED = 'refined';

    public const RESTORED = 'restored';

    public const EDITED = 'edited';

    /**
     * Manual edits within this many minutes of each other share one version, so history stays readable.
     */
    public const EDIT_WINDOW_MINUTES = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Save the roadmap as it stands now.
     */
    public static function record(Goal $goal, string $kind, string $summary): self
    {
        $version = new self;
        $version->forceFill(['goal_id' => $goal->id, 'kind' => $kind, 'summary' => Str::limit($summary, 490), 'snapshot' => PlanEditor::snapshot($goal)])->save();

        return $version;
    }

    /**
     * Log a manual edit. Edits close together are grouped into one version with the latest snapshot.
     */
    public static function recordEdit(Goal $goal, string $description): self
    {
        $latest = $goal->planVersions()->latest('id')->first();

        if ($latest?->kind === self::EDITED && $latest->updated_at->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES))) {
            $latest->forceFill([
                'summary' => Str::limit($latest->summary.' · '.$description, 490),
                'snapshot' => PlanEditor::snapshot($goal),
            ])->save();

            return $latest;
        }

        return self::record($goal, self::EDITED, $description);
    }

    /**
     * "Refined with the coach", "Your edits", and so on.
     */
    public function label(): string
    {
        return match ($this->kind) {
            self::BUILT => 'Plan built',
            self::REFINED => 'Refined with the coach',
            self::RESTORED => 'Restored an earlier version',
            default => 'Your edits',
        };
    }
}
