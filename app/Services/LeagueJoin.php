<?php

namespace App\Services;

use App\Atomic;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\User;
use App\Notifications\LeagueMemberJoined;
use App\PendingInvite;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Joining a GoalLeague, the one way every path does it (invite link, code, the join goal step): locked on the league
 * and re-checked, so a double tap joins once; the invite's use is claimed; a late joiner starts in the current week;
 * then the chat hears about it and the owner is told.
 */
class LeagueJoin
{
    public const FULL = 'full';

    public const OVER = 'over';

    /**
     * Why someone can't join right now, or null if they can.
     */
    public static function blocker(League $league): ?string
    {
        return match (true) {
            $league->isFull() => self::FULL,
            ! $league->isJoinable() => self::OVER,
            default => null,
        };
    }

    /**
     * Join with a goal. Returns false if they were already in.
     *
     * @throws ValidationException when the invite was used up a moment ago
     */
    public function join(League $league, User $user, ?Goal $goal, ?LeagueInvite $invite = null): bool
    {
        try {
            $joined = Atomic::run(function () use ($league, $user, $goal, $invite) {
                $locked = League::whereKey($league->id)->lockForUpdate()->first()->load('members');

                if ($locked->members->contains('user_id', $user->id)) {
                    return false;
                }

                if (! $locked->isJoinable()) {
                    throw ValidationException::withMessages(['goal' => 'This GoalLeague just filled up or its season ended.']);
                }

                if ($invite && ! $invite->claim()) {
                    throw ValidationException::withMessages(['goal' => 'This invite was just used up. Ask for a new one.']);
                }

                $locked->members()->create(['user_id' => $user->id, 'goal_id' => $goal?->id])
                    ->forceFill(['joined_week' => $locked->joinedWeekForNewMember()])->save();

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $joined = false;
        }

        if (! $joined) {
            return false;
        }

        PendingInvite::forget($user);
        $league->announce($user->name.' joined the league.');

        if (! $league->commissioner->is($user)) {
            $league->commissioner->notify(new LeagueMemberJoined($league->withoutRelations(), $user));
        }

        return true;
    }

    /**
     * The friendly line after joining: kickoff, or the week they start in.
     */
    public static function welcome(League $league): string
    {
        $week = $league->joinedWeekForNewMember();

        return $week === null
            ? "You're in {$league->name}. Kickoff is {$league->starts_on->format('l, F j')}."
            : "You're in {$league->name}. You play from GoalWeek {$week}; the weeks before you joined don't count against you.";
    }

    /**
     * The name for the next season of a league: "Run Club" becomes "Run Club · Season 2", and Season 2 becomes 3.
     */
    public static function nextSeasonName(string $name): string
    {
        if (preg_match('/^(.*?)[\s,·-]*Season (\d+)(?: complete)?$/u', $name, $match)) {
            return trim($match[1]).' · Season '.((int) $match[2] + 1);
        }

        return Str::limit($name, 66, '').' · Season 2';
    }
}
