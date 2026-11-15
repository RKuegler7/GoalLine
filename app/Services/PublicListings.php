<?php

namespace App\Services;

use App\GoalCategory;
use App\Models\Challenge;
use App\Models\League;
use App\Models\User;
use App\Models\UserMute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Public GoalLeagues and Challenges anyone can find and join: one place for the query, used by Explore (Highlights)
 * and the GoalLeagues tab's "Find a public…" rows and full lists. Only what an owner set Public, only from the
 * viewer's own world (demo accounts see only demo examples, so they can never join a real league), never from
 * someone they muted, and only while there's room and it isn't over. Cards show the name, kind, size, dates and the
 * owner's name; never emails, notes, intake answers or plans.
 */
class PublicListings
{
    public const ROW = 6;

    public const PAGE = 24;

    public const SIZES = ['small' => 'Up to 8', 'medium' => '9 to 20', 'large' => 'More than 20'];

    public const WHEN = ['soon' => 'Starting soon', 'underway' => 'Under way'];

    public const LEAGUE_DURATIONS = ['short' => 'Up to 6 weeks', 'medium' => '7 to 12 weeks', 'long' => 'Longer'];

    public const CHALLENGE_DURATIONS = ['short' => 'Up to 2 weeks', 'month' => 'About a month', 'long' => 'Longer'];

    /**
     * The filters from the request, checked.
     *
     * @return array<string, string|null>
     */
    public static function filters(Request $request, bool $challenges = false): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'kind' => ['nullable', $challenges ? Rule::in(array_keys(Challenge::TYPES)) : Rule::enum(GoalCategory::class)],
            'when' => ['nullable', Rule::in(array_keys(self::WHEN))],
            'size' => ['nullable', Rule::in(array_keys(self::SIZES))],
            'duration' => ['nullable', Rule::in(array_keys($challenges ? self::CHALLENGE_DURATIONS : self::LEAGUE_DURATIONS))],
        ]);
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Collection<int, League>
     */
    public static function leagues(User $viewer, array $filters = [], int $limit = self::ROW): Collection
    {
        $today = now()->toDateString();

        return League::query()
            ->where('is_public', true)
            ->whereDate('ends_on', '>=', $today)
            ->whereHas('commissioner', fn (Builder $owner) => $owner->where('demo_sandbox', $viewer->demo_sandbox))
            ->whereNotIn('commissioner_id', UserMute::where('user_id', $viewer->id)->select('muted_user_id'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('category', $kind))
            ->when(($filters['when'] ?? null) === 'soon', fn (Builder $query) => $query->whereDate('starts_on', '>', $today))
            ->when(($filters['when'] ?? null) === 'underway', fn (Builder $query) => $query->whereDate('starts_on', '<=', $today))
            ->when($filters['duration'] ?? null, fn (Builder $query, string $duration) => self::lengthFilter($query, $duration, [42, 84]))
            ->withCount('members')
            ->with('commissioner:id,name')
            ->when($filters['size'] ?? null, fn (Builder $query, string $size) => self::sizeFilter($query, $size))
            ->has('members', '<', League::MAX_MEMBERS)
            ->orderBy('starts_on')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Collection<int, Challenge>
     */
    public static function challenges(User $viewer, array $filters = [], int $limit = self::ROW): Collection
    {
        $today = Challenge::today()->toDateString();

        return Challenge::query()
            ->public()
            ->whereDate('ends_on', '>=', $today)
            ->whereHas('creator', fn (Builder $owner) => $owner->where('demo_sandbox', $viewer->demo_sandbox))
            ->whereNotIn('creator_id', UserMute::where('user_id', $viewer->id)->select('muted_user_id'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('type', $kind))
            ->when(($filters['when'] ?? null) === 'soon', fn (Builder $query) => $query->whereDate('starts_on', '>', $today))
            ->when(($filters['when'] ?? null) === 'underway', fn (Builder $query) => $query->whereDate('starts_on', '<=', $today))
            ->when($filters['duration'] ?? null, fn (Builder $query, string $duration) => self::lengthFilter($query, $duration, [14, 45]))
            ->withCount('members')
            ->with('creator:id,name')
            ->when($filters['size'] ?? null, fn (Builder $query, string $size) => self::sizeFilter($query, $size))
            // Room left: no cap, or under it.
            ->whereRaw('(max_members is null or (select count(*) from challenge_members where challenge_members.challenge_id = challenges.id) < max_members)')
            ->orderBy('starts_on')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Builder<League>|Builder<Challenge>  $query
     */
    private static function sizeFilter(Builder $query, string $size): void
    {
        match ($size) {
            'small' => $query->has('members', '<=', 8),
            'medium' => $query->has('members', '>', 8)->has('members', '<=', 20),
            default => $query->has('members', '>', 20),
        };
    }

    /**
     * Length in days between the start and end dates: up to $limits[0], up to $limits[1], or longer.
     *
     * @param  Builder<League>|Builder<Challenge>  $query
     * @param  array{0: int, 1: int}  $limits
     */
    private static function lengthFilter(Builder $query, string $duration, array $limits): void
    {
        $days = $query->getConnection()->getDriverName() === 'sqlite'
            ? '(julianday(ends_on) - julianday(starts_on) + 1)'
            : '(datediff(ends_on, starts_on) + 1)';

        match ($duration) {
            'short' => $query->whereRaw("{$days} <= ?", [$limits[0]]),
            'long' => $query->whereRaw("{$days} > ?", [$limits[1]]),
            default => $query->whereRaw("{$days} > ? and {$days} <= ?", [$limits[0], $limits[1]]),
        };
    }
}
