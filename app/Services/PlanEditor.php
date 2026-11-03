<?php

namespace App\Services;

use App\Clock;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\PlanVersion;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Changes to a roadmap that keep scoring fair: only GoalWeeks that haven't started can change. A GoalWeek's plan locks
 * when it starts (Monday 12:00 a.m. on the member's clock), and no change can take a week below its plan floor. Past check-ins, completed tasks, group tasks, and personal tasks are never
 * touched. A proposal lists, for each GoalWeek it changes, the focus, milestone, and the coach's tasks in order.
 */
class PlanEditor
{
    public function __construct(private WeekPlanner $weekPlanner) {}

    /**
     * GoalWeeks that can change: those that haven't started. A week's plan locks when it starts.
     *
     * @return Collection<int, RoadmapWeek>
     */
    public function editableWeeks(Goal $goal): Collection
    {
        if (! $goal->isActive()) {
            return collect();
        }

        return $goal->weeks
            ->filter(fn (RoadmapWeek $week) => $week->starts_on->gt(Clock::today()))
            ->values();
    }

    /**
     * Whether anything in a GoalWeek is checked off: a task, the milestone, or a habit day.
     */
    public static function hasCheckIns(RoadmapWeek $week): bool
    {
        return $week->milestone_hit_at !== null
            || $week->tasks->contains(fn (RoadmapTask $task) => $task->completed_at !== null)
            || $week->habits()->contains(fn (Habit $habit) => $habit->doneIn($week) > 0);
    }

    /**
     * The coach's own tasks: not bonus, personal, or group tasks, which a refinement leaves alone.
     */
    public static function isPlanTask(RoadmapTask $task): bool
    {
        return ! $task->is_bonus && ! $task->is_personal && ! $task->isGroupTask();
    }

    /**
     * The whole roadmap as plain data, for plan history.
     *
     * @return list<array{position: int, starts_on: string, ends_on: string, focus: string, milestone: string, tasks: list<array{description: string, points: int, kind: string, done: bool}>}>
     */
    public static function snapshot(Goal $goal): array
    {
        return $goal->weeks()->with('tasks')->get()->map(fn (RoadmapWeek $week) => [
            'position' => $week->position,
            'starts_on' => $week->starts_on->toDateString(),
            'ends_on' => $week->ends_on->toDateString(),
            'focus' => $week->focus,
            'milestone' => $week->milestone,
            'tasks' => $week->tasks->map(fn (RoadmapTask $task) => [
                'description' => $task->description,
                'points' => $task->points,
                'kind' => match (true) {
                    $task->isGroupTask() => 'group',
                    (bool) $task->is_personal => 'personal',
                    (bool) $task->is_bonus => 'bonus',
                    default => 'plan',
                },
                'done' => $task->completed_at !== null,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * A proposal that brings back an earlier version's GoalWeeks, for the weeks that can still change.
     *
     * @return list<array{position: int, focus: string, milestone: string, tasks: list<string>}>
     */
    public function proposalFromVersion(Goal $goal, PlanVersion $version): array
    {
        $editable = $this->editableWeeks($goal)->keyBy(fn (RoadmapWeek $week) => $week->starts_on->toDateString());

        return collect($version->snapshot)
            ->filter(fn (array $week) => $editable->has($week['starts_on']))
            ->map(fn (array $week) => [
                'position' => $editable[$week['starts_on']]->position,
                'focus' => $week['focus'],
                'milestone' => $week['milestone'],
                'tasks' => collect($week['tasks'])->where('kind', 'plan')->pluck('description')->values()->all(),
            ])
            ->filter(fn (array $week) => $week['tasks'] !== [])
            ->values()
            ->all();
    }

    /**
     * What a proposal would change, GoalWeek by GoalWeek: focus and milestone, tasks added, removed, and moved between
     * weeks, the order, and planned points before and after. GoalWeeks that can no longer change are listed apart.
     *
     * @param  list<array{position: int, focus: string, milestone: string, tasks: list<string>}>  $proposal
     * @return array{weeks: list<array<string, mixed>>, skipped: list<int>}
     */
    public function diff(Goal $goal, array $proposal): array
    {
        $editable = $this->editableWeeks($goal)->keyBy('position');
        $weeks = [];
        $skipped = [];

        foreach ($proposal as $proposed) {
            $week = $editable->get($proposed['position']);

            if ($week === null) {
                $skipped[] = $proposed['position'];

                continue;
            }

            $current = $week->tasks->filter(fn (RoadmapTask $task) => self::isPlanTask($task))->sortBy('position')->values();
            $currentKeys = $current->map(fn (RoadmapTask $task) => self::key($task->description));
            $proposedKeys = collect($proposed['tasks'])->map(fn (string $task) => self::key($task));
            $removed = $current->reject(fn (RoadmapTask $task) => $proposedKeys->contains(self::key($task->description)));
            $added = collect($proposed['tasks'])->reject(fn (string $task) => $currentKeys->contains(self::key($task)))->values();
            $kept = $proposedKeys->filter(fn (string $key) => $currentKeys->contains($key))->values();

            $weeks[] = [
                'week' => $week,
                'focus' => $week->focus !== $proposed['focus'] ? [$week->focus, $proposed['focus']] : null,
                'milestone' => $week->milestone !== $proposed['milestone'] ? [$week->milestone, $proposed['milestone']] : null,
                'added' => $added->all(),
                'removed' => $removed->pluck('description')->values()->all(),
                'moved' => [],
                'reordered' => $kept->all() !== $currentKeys->filter(fn (string $key) => $kept->contains($key))->values()->all(),
                'pointsBefore' => $week->plannedPoints(),
                // New tasks are worth 1 point, like any regular task. A week that would fall below its floor is raised.
                'pointsAfter' => max($week->effectiveFloor() ?? 0, $week->plannedPoints() - (int) $removed->sum('points') + $added->count()),
                'raised' => $week->effectiveFloor() !== null && $week->plannedPoints() - (int) $removed->sum('points') + $added->count() < $week->effectiveFloor(),
                'floor' => $week->effectiveFloor(),
            ];
        }

        // A task taken out of one GoalWeek and put into another is a move.
        foreach ($weeks as $from => $source) {
            foreach ($source['removed'] as $removedIndex => $description) {
                foreach ($weeks as $to => $target) {
                    $addedIndex = collect($target['added'])->search(fn (string $task) => self::key($task) === self::key($description));

                    if ($to !== $from && $addedIndex !== false) {
                        $weeks[$to]['moved'][] = ['description' => $target['added'][$addedIndex], 'from' => $source['week']->label()];
                        unset($weeks[$from]['removed'][$removedIndex], $weeks[$to]['added'][$addedIndex]);
                        $weeks[$to]['added'] = array_values($weeks[$to]['added']);

                        break;
                    }
                }
            }

            $weeks[$from]['removed'] = array_values($weeks[$from]['removed']);
        }

        $changed = array_values(array_filter($weeks, fn (array $week) => $week['focus'] || $week['milestone'] || $week['added'] || $week['removed'] || $week['moved'] || $week['reordered'] || $week['raised']));

        return ['weeks' => $changed, 'skipped' => $skipped];
    }

    /**
     * Apply a proposal to the GoalWeeks that can still change, and save the result to plan history. Tasks the
     * proposal keeps stay as they are (with their numbers and days); new ones are worth 1 point and get spread over
     * the week's days.
     *
     * @param  list<array{position: int, focus: string, milestone: string, tasks: list<string>}>  $proposal
     * @return int How many GoalWeeks changed.
     */
    public function apply(Goal $goal, array $proposal, string $kind, string $summary): int
    {
        $goal->load('weeks.tasks', 'habits.logs');
        $editable = $this->editableWeeks($goal)->keyBy('position');
        $changed = 0;

        DB::transaction(function () use ($proposal, $editable, &$changed) {
            foreach ($proposal as $proposed) {
                $week = $editable->get($proposed['position']);

                if ($week === null) {
                    continue;
                }

                $week->update(['focus' => $proposed['focus'], 'milestone' => $proposed['milestone']]);
                $existing = $week->tasks->filter(fn (RoadmapTask $task) => self::isPlanTask($task))->groupBy(fn (RoadmapTask $task) => self::key($task->description));
                $kept = [];

                foreach ($proposed['tasks'] as $index => $description) {
                    $task = $existing->get(self::key($description))?->first(fn (RoadmapTask $candidate) => ! in_array($candidate->id, $kept, true))
                        ?? $week->tasks()->make(['description' => $description]);
                    $task->forceFill(['position' => $index + 1])->save();
                    $kept[] = $task->id;
                }

                $week->tasks->filter(fn (RoadmapTask $task) => self::isPlanTask($task) && ! in_array($task->id, $kept, true))->each->delete();

                // Group, personal, and bonus tasks stay, after the coach's tasks.
                $week->tasks->reject(fn (RoadmapTask $task) => self::isPlanTask($task))->values()
                    ->each(fn (RoadmapTask $task, int $index) => $task->forceFill(['position' => count($proposed['tasks']) + $index + 1])->save());

                $this->weekPlanner->autoPlan($week->load('tasks'));
                app(PlanFloor::class)->raise($week);
                $changed++;
            }
        });

        if ($changed > 0) {
            PlanVersion::record($goal, $kind, $summary);
        }

        return $changed;
    }

    /**
     * Task text compared loosely: case, spacing, and a final period don't matter.
     */
    public static function key(string $description): string
    {
        return rtrim(Str::lower(preg_replace('/\s+/', ' ', trim($description))), '.');
    }
}
