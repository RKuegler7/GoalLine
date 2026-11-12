<?php

namespace App\Services;

use App\Clock;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\LeagueMember;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;

/**
 * Finds check-ins that were made before they could count (before the goal's kickoff, or on a GoalWeek that hadn't
 * started) and voids them: the row stays, with what it was and why, but it's no longer checked off, so scoring,
 * percent of plan, streaks, stars, standings, recaps and share cards all ignore it. Settled league weeks that included
 * one are re-scored. Idempotent: running it again finds nothing new.
 */
class EarlyCheckIns
{
    public const REASON = 'before kickoff';

    /**
     * @return array{tasks: int, proofs: int, milestones: int, habit_logs: int, rescored_weeks: int}
     */
    public function void(bool $dryRun = false): array
    {
        $counts = ['tasks' => 0, 'proofs' => 0, 'milestones' => 0, 'habit_logs' => 0, 'rescored_weeks' => 0];
        $touched = [];

        $goalIds = RoadmapTask::query()->whereNotNull('completed_at')->join('roadmap_weeks', 'roadmap_weeks.id', '=', 'roadmap_tasks.roadmap_week_id')->distinct()->pluck('roadmap_weeks.goal_id')
            ->merge(RoadmapWeek::query()->whereNotNull('milestone_hit_at')->distinct()->pluck('goal_id'))
            ->merge(HabitLog::query()->whereNull('voided_at')->join('habits', 'habits.id', '=', 'habit_logs.habit_id')->distinct()->pluck('habits.goal_id'))
            ->unique();

        foreach (Goal::query()->whereIn('id', $goalIds)->with('weeks.tasks', 'habits')->lazyById(100) as $goal) {
            $starts = $goal->checkInSeason()['starts']->toDateString();

            foreach ($goal->weeks as $week) {
                $opens = max($starts, $week->starts_on->toDateString());
                $weekTouched = false;

                foreach ($week->tasks as $task) {
                    if ($task->completed_at && Clock::local($task->completed_at)->toDateString() < $opens) {
                        $counts['tasks']++;
                        $counts['proofs'] += $task->proof_path ? 1 : 0;
                        $weekTouched = true;

                        if (! $dryRun) {
                            $task->forceFill([
                                'voided_at' => now(),
                                'void_reason' => self::REASON,
                                'voided_completed_at' => $task->completed_at,
                                'voided_proof_path' => $task->proof_path,
                                'completed_at' => null,
                                'proof_path' => null,
                                'proof_mime_type' => null,
                                'proof_uploaded_at' => null,
                            ])->saveQuietly();
                        }
                    }
                }

                if ($week->milestone_hit_at && Clock::local($week->milestone_hit_at)->toDateString() < $opens) {
                    $counts['milestones']++;
                    $weekTouched = true;

                    if (! $dryRun) {
                        $week->forceFill(['milestone_voided_at' => $week->milestone_hit_at, 'milestone_hit_at' => null])->saveQuietly();
                    }
                }

                if ($weekTouched) {
                    $touched[] = $week;
                }
            }

            $early = HabitLog::query()->whereIn('habit_id', $goal->habits->pluck('id'))->whereNull('voided_at')->whereDate('logged_on', '<', $starts);
            $counts['habit_logs'] += (clone $early)->count();

            if (! $dryRun) {
                $early->update(['voided_at' => now(), 'void_reason' => self::REASON]);
            }
        }

        if (! $dryRun) {
            $counts['rescored_weeks'] = $this->rescore($touched);
        }

        return $counts;
    }

    /**
     * Re-score settled league weeks whose GoalWeek had a voided check-in.
     *
     * @param  list<RoadmapWeek>  $weeks
     */
    private function rescore(array $weeks): int
    {
        $rescored = 0;

        foreach ($weeks as $week) {
            foreach (LeagueMember::where('goal_id', $week->goal_id)->with('league')->get() as $member) {
                $league = $member->league;
                $number = $league?->weekNumberOn($week->ends_on);

                if ($number !== null && $league->results()->where('week', $number)->exists()) {
                    (new LeagueTable($league))->rescore($number);
                    $rescored++;
                }
            }
        }

        return $rescored;
    }
}
