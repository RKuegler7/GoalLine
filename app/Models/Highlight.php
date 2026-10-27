<?php

namespace App\Models;

use App\HighlightKind;
use App\PostVisibility;
use App\ProfileVisibility;
use Database\Factories\HighlightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Something shared to the feed: a finished goal, a great GoalWeek, a milestone, or a check-in post
 * (a photo added to a checked-off task).
 */
#[Fillable(['kind', 'goal_id', 'roadmap_week_id', 'roadmap_task_id', 'league_id', 'visibility', 'location', 'headline', 'detail', 'caption'])]
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
     * Longest caption on a check-in post, in characters.
     */
    public const MAX_POST_CAPTION = 280;

    /**
     * Longest "Where?", in characters.
     */
    public const MAX_LOCATION = 60;

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
            'visibility' => PostVisibility::class,
        ];
    }

    /**
     * The one rule for who sees what, used by the feeds and the policy alike:
     *
     *  - your own posts, always;
     *  - check-in posts: "League" ones to that GoalLeague's members, "League + friends" ones to the author's friends too;
     *  - other highlights: the author's friends and GoalLeague mates, unless the author's locker is private;
     *  - never a post you reported.
     *
     * @param  Builder<Highlight>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $friendIds = $viewer->friendIds();
        $leagueIds = $viewer->leagues()->pluck('leagues.id');
        $circle = $friendIds->merge($viewer->leagueMateIds())->unique()->values();

        $query
            ->where(fn (Builder $visible) => $visible
                ->where('highlights.user_id', $viewer->id)
                ->orWhere(fn (Builder $post) => $post
                    ->where('kind', HighlightKind::CheckIn)
                    ->where(fn (Builder $audience) => $audience
                        ->where(fn (Builder $league) => $league
                            ->whereIn('visibility', [PostVisibility::League, PostVisibility::LeagueFriends])
                            ->whereIn('league_id', $leagueIds))
                        ->orWhere(fn (Builder $friends) => $friends
                            ->where('visibility', PostVisibility::LeagueFriends)
                            ->whereIn('highlights.user_id', $friendIds))))
                ->orWhere(fn (Builder $win) => $win
                    ->where('kind', '!=', HighlightKind::CheckIn)
                    ->whereIn('highlights.user_id', $circle)
                    ->whereHas('user', fn (Builder $author) => $author->where('profile_visibility', '!=', ProfileVisibility::Private))))
            ->whereDoesntHave('reports', fn (Builder $reports) => $reports->where('reporter_id', $viewer->id));
    }

    /**
     * Whether this is a check-in post: a photo from a checked-off task.
     */
    public function isPost(): bool
    {
        return $this->kind === HighlightKind::CheckIn;
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
     * The checked-off task a check-in post shows, while it still exists.
     *
     * @return BelongsTo<RoadmapTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(RoadmapTask::class, 'roadmap_task_id');
    }

    /**
     * The GoalLeague a check-in post was shared to.
     *
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
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
     * Reports from people who'd rather not see it.
     *
     * @return HasMany<PostReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
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
