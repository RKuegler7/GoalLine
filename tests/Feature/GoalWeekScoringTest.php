<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\MatchupOutcome;
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
     * @return array<string, array{float, GoalWeekResult}>
     */
    public static function bands(): array
    {
        return [
            'nothing done is short' => [0.0, GoalWeekResult::Short],
            '89% is short' => [0.89, GoalWeekResult::Short],
            '89.99% is short' => [0.8999, GoalWeekResult::Short],
            '90% is in line' => [0.90, GoalWeekResult::InLine],
            '100% is in line' => [1.00, GoalWeekResult::InLine],
            '101% is past' => [1.01, GoalWeekResult::Past],
            '149% is past' => [1.49, GoalWeekResult::Past],
            '149.99% is past' => [1.4999, GoalWeekResult::Past],
            '150% is smashed' => [1.50, GoalWeekResult::Smashed],
            'smashed is uncapped' => [3.25, GoalWeekResult::Smashed],
        ];
    }

    #[DataProvider('bands')]
    public function test_the_share_of_planned_points_sets_the_band(float $share, GoalWeekResult $expected): void
    {
        $this->assertSame($expected, GoalWeekResult::score($share));
    }

    public function test_a_smashed_share_without_proof_or_confirmation_counts_as_past(): void
    {
        $this->assertSame(GoalWeekResult::Past, GoalWeekResult::score(1.50, smashVerified: false));
        $this->assertSame(GoalWeekResult::Past, GoalWeekResult::score(2.40, smashVerified: false));
        $this->assertSame(GoalWeekResult::InLine, GoalWeekResult::score(0.95, smashVerified: false));
    }

    public function test_line_points_run_from_0_to_3(): void
    {
        $this->assertSame([0, 1, 2, 3], array_map(fn (GoalWeekResult $result) => $result->points(), GoalWeekResult::cases()));
    }

    public function test_match_points_better_band_wins_2_same_band_draws_1(): void
    {
        foreach (GoalWeekResult::cases() as $mine) {
            foreach (GoalWeekResult::cases() as $theirs) {
                $outcome = MatchupOutcome::compare($mine->points(), $theirs->points());
                $expected = $mine->points() <=> $theirs->points();

                $this->assertSame([1 => 2, 0 => 1, -1 => 0][$expected], $outcome->points(), "{$mine->value} vs {$theirs->value}");

                // A week is worth line points plus match points: 0 to 5.
                $this->assertGreaterThanOrEqual(0, $mine->points() + $outcome->points());
                $this->assertLessThanOrEqual(5, $mine->points() + $outcome->points());
            }
        }

        $this->assertSame(5, GoalWeekResult::Smashed->points() + MatchupOutcome::compare(3, 2)->points());
    }

    public function test_planned_and_earned_points_include_tasks_habits_and_the_milestone(): void
    {
        [$goal, $week] = $this->goalWithWeek(today()->subDays(2), tasks: 4, done: 3);
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        $habit->logs()->create(['logged_on' => today()]);
        $week = $week->fresh(['tasks', 'goal.habits.logs']);

        // 4 tasks + 3 habit days + milestone (2) = 9 planned; 3 tasks + 1 habit day = 4 earned.
        $this->assertSame(9, $week->plannedPoints());
        $this->assertEqualsWithDelta(4.0, $week->earnedPoints(), 0.001);
        $this->assertSame(GoalWeekResult::Short, $week->result());
    }

    public function test_day_one_shows_not_started_until_a_check_in_then_the_real_result(): void
    {
        [$goal, $week] = $this->goalWithWeek(today(), tasks: 3);

        $this->assertTrue($week->isNotStarted());
        $this->actingAs($goal->user)->get(route('goals.show', $goal))
            ->assertSee('Not started')
            ->assertSee('New GoalWeek. Trust the process: check off your first task to get going.');

        $this->travel(1)->days();

        $this->assertFalse($week->fresh('tasks')->isNotStarted());
        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('Short of the line');
    }

    public function test_checking_off_tasks_the_milestone_and_bonus_work_moves_through_the_bands(): void
    {
        Queue::fake();
        [$goal, $week] = $this->goalWithWeek(today()->subDays(2), tasks: 4);

        foreach ($week->tasks as $task) {
            $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $task))
                ->assertRedirect(route('goals.show', $goal).'#goalweek-1');
        }
        // 4 of 6 planned points.
        $this->assertSame(GoalWeekResult::Short, $week->fresh('tasks')->result());

        $this->actingAs($goal->user)->post(route('goalweeks.milestone', $week));
        $this->assertSame(GoalWeekResult::InLine, $week->fresh('tasks')->result()); // 6 of 6

        // Bonus work beyond the plan (from before personal tasks were capped) still counts toward the result.
        $bonus = $week->tasks()->create(['position' => 99, 'description' => 'Extra set', 'is_bonus' => true]);
        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $bonus));
        $this->assertSame(GoalWeekResult::Past, $week->fresh('tasks')->result()); // 7 of 6

        $this->actingAs($goal->user)->post(route('goalweeks.milestone', $week));
        $this->assertNull($week->fresh()->milestone_hit_at);
    }

    public function test_close_call_says_how_many_points_get_you_safely_in_line(): void
    {
        // 10 tasks + milestone (2) = 12 planned; 11 earned is 91.7%: in line, but a close call.
        [$goal, $week] = $this->goalWithWeek(today()->subDays(2), tasks: 10, done: 9, milestone: true);

        $week = $week->fresh('tasks');
        $this->assertTrue($week->isCloseCall());
        $this->assertSame(1, $week->pointsToSafety());

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('In line, close call')
            ->assertSee('Close call: 1 more point gets you safely in line.');
    }

    public function test_tasks_members_add_are_personal_and_count_in_the_plan(): void
    {
        Queue::fake();
        [$goal, $current] = $this->goalWithWeek(today()->subDays(2), tasks: 2);
        $upcoming = $goal->weeks()->create([
            'position' => 2, 'starts_on' => today()->addDays(5), 'ends_on' => today()->addDays(11),
            'focus' => 'Next', 'milestone' => 'Next milestone.',
        ]);
        $upcoming->tasks()->create(['position' => 1, 'description' => 'Planned work'])->forceFill(['points' => 20])->save();
        $plannedBefore = $upcoming->fresh('tasks')->plannedPoints();

        $this->actingAs($goal->user)->post(route('roadmap.tasks.store', $upcoming), ['description' => 'Plan ahead', 'points' => 2]);
        $added = $upcoming->tasks()->where('description', 'Plan ahead')->sole();
        $this->assertTrue($added->is_personal);
        $this->assertFalse($added->is_bonus);
        $this->assertSame($plannedBefore + 2, $upcoming->fresh('tasks')->plannedPoints());

        // Past Tuesday of a GoalWeek, nothing more can be added to it.
        $this->actingAs($goal->user)->post(route('roadmap.tasks.store', $current), ['description' => 'Extra practice set'])
            ->assertSessionHasErrorsIn('week'.$current->id, 'description');
        $this->assertSame(0, $current->tasks()->where('description', 'Extra practice set')->count());
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
        // Week 1: 2 tasks + milestone, all done (4 of 4): in line, 1 point. Week 2: 2 of 4: short, 0.
        [$goal] = $this->goalWithWeek(today()->subDays(14), tasks: 2, done: 2, milestone: true);
        $this->addWeek($goal, 2, today()->subDays(7), tasks: 2, done: 2);

        $this->assertSame(1, $goal->fresh('weeks.tasks')->points());

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('GoalWeek points')
            ->assertSee('In line');
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
                ->forceFill(['completed_at' => $index <= $done ? now() : null])->saveQuietly();
        }

        return $week->load('tasks');
    }
}
