<?php

namespace App\Models;

use App\HighlightKind;
use Database\Factories\HighlightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['kind', 'goal_id', 'roadmap_week_id', 'headline', 'detail', 'caption'])]
class Highlight extends Model
{
    /** @use HasFactory<HighlightFactory> */
    use HasFactory;

    /**
     * Highlight photos are kept on the private disk and served through a route.
     */
    public const PHOTO_DISK = 'uploads';

    /**
     * Longest caption, in characters.
     */
    public const MAX_CAPTION = 500;

    /**
     * Remove the stored photo along with the highlight.
     */
    protected static function booted(): void
    {
        static::deleted(function (Highlight $highlight) {
            if ($highlight->photo_path) {
                Storage::disk(self::PHOTO_DISK)->delete($highlight->photo_path);
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
            'kind' => HighlightKind::class,
        ];
    }

    /**
     * Who shared it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The goal it celebrates, while that goal still exists.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * The GoalWeek it celebrates, for great weeks and milestones.
     *
     * @return BelongsTo<RoadmapWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(RoadmapWeek::class, 'roadmap_week_id');
    }

    /**
     * Cheers from people who saw it.
     *
     * @return HasMany<Cheer, $this>
     */
    public function cheers(): HasMany
    {
        return $this->hasMany(Cheer::class)->orderBy('id');
    }

    /**
     * Comments, oldest first.
     *
     * @return HasMany<HighlightComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(HighlightComment::class)->orderBy('id');
    }

    /**
     * Where the photo is served from, if there is one.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('highlights.photo', $this) : null;
    }

    /**
     * Whether this person has cheered it.
     */
    public function isCheeredBy(User $user): bool
    {
        return $this->cheers->contains('user_id', $user->id);
    }

    /**
     * "Sam and Priya cheered", "Sam, Priya, and 3 others cheered", or "You cheered".
     */
    public function cheerSummary(User $viewer): ?string
    {
        if ($this->cheers->isEmpty()) {
            return null;
        }

        $names = $this->cheers->sortByDesc(fn (Cheer $cheer) => $cheer->user_id === $viewer->id)
            ->map(fn (Cheer $cheer) => $cheer->user_id === $viewer->id ? 'You' : $cheer->user->name)
            ->values();

        $shown = $names->take(2);
        $others = $names->count() - $shown->count();

        return ($others > 0
            ? $shown->implode(', ').", and {$others} ".($others === 1 ? 'other' : 'others')
            : $shown->join(', ', ' and ')).' cheered';
    }
}
