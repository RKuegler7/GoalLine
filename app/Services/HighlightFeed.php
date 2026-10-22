<?php

namespace App\Services;

use App\Models\Highlight;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class HighlightFeed
{
    public function __construct(private User $viewer) {}

    /**
     * Highlights from people the viewer follows, their GoalLeague mates, and themselves, newest first,
     * keeping only the ones the viewer is allowed to see.
     *
     * @return Collection<int, Highlight>
     */
    public function latest(int $limit, ?int $before = null): Collection
    {
        $authors = $this->viewer->following()->pluck('users.id')
            ->merge($this->viewer->leagueMateIds())
            ->push($this->viewer->id)
            ->unique();

        return Highlight::query()
            ->whereIn('user_id', $authors)
            ->when($before, fn ($query) => $query->where('id', '<', $before))
            ->with(['user', 'cheers.user', 'comments.user'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->filter(fn (Highlight $highlight) => Gate::forUser($this->viewer)->allows('view', $highlight))
            ->values();
    }
}
