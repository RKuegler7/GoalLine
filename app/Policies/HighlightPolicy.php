<?php

namespace App\Policies;

use App\Models\Highlight;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class HighlightPolicy
{
    /**
     * Highlights go to the author's followers and GoalLeague mates, as long as the author's locker is visible to them.
     */
    public function view(User $viewer, Highlight $highlight): bool
    {
        $author = $highlight->user;

        if ($viewer->is($author)) {
            return true;
        }

        if (Gate::forUser($viewer)->denies('viewProfile', $author)) {
            return false;
        }

        return $viewer->follows($author) || $viewer->sharesLeagueWith($author);
    }

    /**
     * Only the author can remove a highlight or download its share card.
     */
    public function delete(User $user, Highlight $highlight): bool
    {
        return $highlight->user()->is($user);
    }
}
