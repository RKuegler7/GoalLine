<?php

namespace App\Services;

use App\Badge;
use App\GoalWeekResult;
use App\Models\Cheer;
use App\Models\HabitLog;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeEarned;
use Illuminate\Support\Collection;

class BadgeAwarder
{
    /**
     * Award any personal badges the member has earned but doesn't have yet.
     *
     * @return Collection<int, UserBadge> The newly earned badges.
     */
    public function checkPersonal(User $user): Collection
    {
        $have = $user->badges()->whereNull('league_id')->get()->pluck('badge');
        $missing = collect(Badge::personal())->reject(fn (Badge $badge) => $have->contains($badge));

        if ($missing->isEmpty()) {
            return collect();
        }

        $stats = new ProfileStats($user);
        $finished = $stats->finishedWeeks->map(fn (RoadmapWeek $week) => $week->result());
        $atLeast = fn (GoalWeekResult ...$tiers) => $finished->contains(fn (?GoalWeekResult $result) => in_array($result, $tiers, true));
        $dailyStreak = fn () => (new DailyStreak($user))->current();
        $habitCheckIns = fn () => HabitLog::whereHas('habit.goal', fn ($goals) => $goals->where('user_id', $user->id))->count();

        $earned = [
            Badge::FirstStep->value => fn () => $habitCheckIns() > 0
                || RoadmapTask::whereNotNull('completed_at')->whereHas('week.goal', fn ($goals) => $goals->where('user_id', $user->id))->exists(),
            Badge::InLine->value => fn () => $atLeast(GoalWeekResult::InLine, GoalWeekResult::Past, GoalWeekResult::Smashed),
            Badge::PastTheLine->value => fn () => $atLeast(GoalWeekResult::Past, GoalWeekResult::Smashed),
            Badge::Smashed->value => fn () => $atLeast(GoalWeekResult::Smashed),
            Badge::OnARoll->value => fn () => $stats->bestStreak() >= Badge::ON_A_ROLL_WEEKS,
            Badge::IronWill->value => fn () => $stats->bestStreak() >= Badge::IRON_WILL_WEEKS,
            Badge::HabitHero->value => fn () => $habitCheckIns() >= Badge::HABIT_HERO_CHECK_INS,
            Badge::Finisher->value => fn () => $stats->completedGoals()->isNotEmpty(),
            Badge::TeamPlayer->value => fn () => $user->leagues()->exists(),
            Badge::HypeSquad->value => fn () => Cheer::where('user_id', $user->id)->count() >= Badge::HYPE_SQUAD_CHEERS,
            Badge::WeekWarrior->value => fn () => $dailyStreak() >= Badge::WEEK_WARRIOR_DAYS,
            Badge::Unstoppable->value => fn () => $dailyStreak() >= Badge::UNSTOPPABLE_DAYS,
        ];

        return $missing
            ->filter(fn (Badge $badge) => $earned[$badge->value]())
            ->map(fn (Badge $badge) => $this->award($user, $badge))
            ->filter()
            ->values();
    }

    /**
     * Give a member a badge (or a season award, with its league) and tell them. Null if they already have it.
     */
    public function award(User $user, Badge $badge, ?League $league = null, ?string $detail = null): ?UserBadge
    {
        $exists = $user->badges()->where('badge', $badge->value)->where('league_id', $league?->id)->exists();

        if ($exists) {
            return null;
        }

        $earned = $user->badges()->create([
            'badge' => $badge,
            'league_id' => $league?->id,
            'detail' => $detail,
            'earned_at' => now(),
        ]);

        $user->notify(new BadgeEarned($earned));

        return $earned;
    }
}
