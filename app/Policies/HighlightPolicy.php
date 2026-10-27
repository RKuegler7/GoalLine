<?php

namespace App\Policies;

use App\Models\Highlight;
use App\Models\User;

class HighlightPolicy
{
    /**
     * Who sees a highlight or post follows the one rule in Highlight::scopeVisibleTo, so the feeds and the photo
     * route can never disagree.
     */
    public function view(User $viewer, Highlight $highlight): bool
    {
        return Highlight::query()->visibleTo($viewer)->whereKey($highlight->id)->exists();
    }

    /**
     * Anyone who can see someone else's post can report it.
     */
    public function report(User $viewer, Highlight $highlight): bool
    {
        return $highlight->user_id !== $viewer->id && $this->view($viewer, $highlight);
    }

    /**
     * Only the author can download a share card.
     */
    public function share(User $user, Highlight $highlight): bool
    {
        return $highlight->user_id === $user->id;
    }

    /**
     * The author can remove a highlight; a GoalLeague owner can remove a post shared to their league.
     */
    public function delete(User $user, Highlight $highlight): bool
    {
        return $highlight->user_id === $user->id
            || ($highlight->league_id !== null && $highlight->league?->commissioner_id === $user->id);
    }
}
