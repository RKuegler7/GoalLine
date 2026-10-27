<?php

namespace App\Services;

use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The feeds, newest first. Every one starts from Highlight::scopeVisibleTo, so a feed only ever shows what the
 * viewer is allowed to see.
 */
class HighlightFeed
{
    public function __construct(private User $viewer) {}

    /**
     * Around the League: everything the viewer can see from friends, GoalLeague mates, and themselves.
     *
     * @return Collection<int, Highlight>
     */
    public function latest(int $limit, ?int $before = null): Collection
    {
        return $this->visible()
            ->when($before, fn (Builder $query) => $query->where('highlights.id', '<', $before))
            ->limit($limit)
            ->get();
    }

    /**
     * The Clubhouse's Friends feed: what the viewer's friends shared and the viewer can see.
     *
     * @return Paginator<int, Highlight>
     */
    public function friends(int $perPage): Paginator
    {
        return $this->visible()
            ->whereIn('highlights.user_id', $this->viewer->friendIds())
            ->simplePaginate($perPage, pageName: 'friends');
    }

    /**
     * A GoalLeague's feed: check-in posts shared to it.
     *
     * @return Paginator<int, Highlight>
     */
    public function league(League $league, int $perPage): Paginator
    {
        return $this->visible()
            ->where('league_id', $league->id)
            ->simplePaginate($perPage);
    }

    /**
     * @return Builder<Highlight>
     */
    private function visible(): Builder
    {
        return Highlight::query()
            ->visibleTo($this->viewer)
            ->with(['user', 'cheers.user', 'comments.user', 'task', 'league'])
            ->latest('highlights.id');
    }
}
