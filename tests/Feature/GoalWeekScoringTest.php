<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoalWeekScoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int, int, int, bool, GoalWeekResult}>
     */
    public static function results(): array
    {
        return [
            'under 70% is short' => [10, 6, 0, true, GoalWeekResult::Short],
            '70% is in line' => [10, 7, 0, false, GoalWeekResult::InLine],
            'all tasks without milestone is in line' => [4, 4, 0, false, GoalWeekResult::InLine],
            'all tasks and milestone is past' => [4, 4, 0, true, GoalWeekResult::Past],
            'one bonus on a small week smashes' => [4, 4, 1, true, GoalWeekResult::Smashed],
            'needs 20% bonus on a big week' => [10, 10, 1, true, GoalWeekResult::Past],
            'two bonus on a big week smashes' => [10, 10, 2, true, GoalWeekResult::Smashed],
            'bonus without the milestone stays in line' => [4, 4, 3, false, GoalWeekResult::InLine],
        ];
    }

    #[DataProvider('results')]
    public function test_goalweek_results_follow_the_scoring_rules(int $planned, int $done, int $bonus, bool $milestone, GoalWeekResult $expected): void
    {
        $this->assertSame($expected, GoalWeekResult::score($planned, $done, $bonus, $milestone));
    }

    public function test_result_points(): void
    {
        $this->assertSame([0, 1, 2, 3], array_map(fn (GoalWeekResult $result) => $result->points(), GoalWeekResult::cases()));
    }

    public function test_checking_off_tasks_and_the_milestone_scores_the_current_goalweek(): void
    {
        [$goal, $week] = $this->goalWithWeek(today()->subDays(2), tasks: 4);

        foreach ($week->tasks as $task) {
            $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $task))
                ->assertRedirect(route('goals.show', $goal).'#goalweek-1');
        }
        $this->assertSame(GoalWeekResult::InLine, $week->fresh('tasks')->result());

        $this->actingAs($goal->user)->post(route('goalweeks.milestone', $week));
        $this->assertSame(GoalWeekResult::Past, $week->fresh('tasks')->result());

        $this->actingAs($goal->user)->post(route('goalweeks.milestone', $week));
        $this->assertNull($week->fresh()->milestone_hit_at);
    }

    public function test_close_call_warns_how_many_tasks_get_you_safely_in_line(): void
    {
        [$goal, $week] = $this->goalWithWeek(today()->subDays(2), tasks: 10);
        $week->tasks->take(7)->each(fn ($task) => $task->forceFill(['completed_at' => now()])->save());

        $week = $week->fresh('tasks');
        $this->assertTrue($week->isCloseCall());
        $this->assertSame(1, $week->tasksToSafety());

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('In line, close call')
            ->assertSee('Close call: 1 more task gets you safely in line.');
    }

    public function test_tasks_added_during_the_goalweek_are_bonus_but_earlier_ones_are_planned(): void
    {
        Queue::fake();
        [$goal, $current] = $this->goalWithWeek(today()->subDays(2), tasks: 2);
        $upcoming = $goal->weeks()->create([
            'position' => 2, 'starts_on' => today()->addDays(5), 'ends_on' => today()->addDays(11),
            'focus' => 'Next', 'milestone' => 'Next milestone.',
        ]);

        $this->actingAs($goal->user)->post(route('roadmap.tasks.store', $current), ['description' => 'Extra practice set']);
        $this->actingAs($goal->user)->post(route('roadmap.tasks.store', $upcoming), ['description' => 'Plan ahead']);

        $this->assertTrue($current->tasks()->where('description', 'Extra practice set')->sole()->is_bonus);
        $this->assertFalse($upcoming->tasks()->sole()->is_bonus);
    }

    public function test_upcoming_goalweeks_cannot_be_checked_off_yet(): void
    {
        [$goal, $week] = $this->goalWithWeek(today()->addDays(3), tasks: 1);

        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $week->tasks->first()))->assertForbidden();
        $this->assertNull($week->result());
    }

    public function test_check_ins_stay_open_one_day_after_the_goalweek_then_lock(): void
    {
        [$goal, $week] = $this->goalWithWeek(today()->subDays(7), tasks: 1);
        $this->assertTrue($week->isCheckable());

        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $week->tasks->first()))->assertRedirect();

        $this->travel(1)->days();
        $this->assertTrue($week->fresh()->isLocked());
        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $week->tasks->first()))->assertForbidden();
    }

    public function test_only_the_owner_can_check_in(): void
    {
        [, $week] = $this->goalWithWeek(today()->subDays(2), tasks: 1);

        $this->actingAs(User::factory()->create())
            ->post(route('roadmap.tasks.check', $week->tasks->first()))
            ->assertForbidden();
    }

    public function test_goal_points_add_up_goalweek_results(): void
    {
        [$goal] = $this->goalWithWeek(today()->subDays(14), tasks: 2, done: 2, milestone: true);
        $this->addWeek($goal, 2, today()->subDays(7), tasks: 2, done: 2);

        $this->assertSame(3, $goal->fresh('weeks.tasks')->points());

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('GoalWeek points')
            ->assertSee('Past the line');
    }

    /**
     * @return array{Goal, RoadmapWeek}
     */
    private function goalWithWeek($startsOn, int $tasks, int $done = 0, bool $milestone = false): array
    {
        $goal = Goal::factory()->create(['target_date' => today()->addDays(30)]);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'A plan.'])->save();

        return [$goal, $this->addWeek($goal, 1, $startsOn, $tasks, $done, $milestone)];
    }

    private function addWeek(Goal $goal, int $position, $startsOn, int $tasks, int $done = 0, bool $milestone = false): RoadmapWeek
    {
        $week = $goal->weeks()->create([
            'position' => $position,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(6),
            'focus' => 'Focus '.$position,
            'milestone' => 'Milestone '.$position,
        ]);
        $week->forceFill(['milestone_hit_at' => $milestone ? now() : null])->save();

        foreach (range(1, $tasks) as $index) {
            $week->tasks()->create(['position' => $index, 'description' => "Task {$index}"])
                ->forceFill(['completed_at' => $index <= $done ? now() : null])->save();
        }

        return $week->load('tasks');
    }
}
