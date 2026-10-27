<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Jobs\GenerateRoadmap;
use App\Jobs\ReviewRoadmapChanges;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class HabitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday morning.
    }

    public function test_habit_check_ins_count_toward_the_goalweek_result(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek();
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        $week->tasks->each(fn ($task) => $task->forceFill(['completed_at' => now()])->save());

        // 2 tasks + 3 runs + milestone (2) = 7 planned points; 2 tasks done and no runs yet is 2 of 7.
        $this->assertSame(7, $this->fresh($week)->plannedPoints());
        $this->assertSame(GoalWeekResult::Short, $this->fresh($week)->result());

        $habit->logs()->create(['logged_on' => '2026-10-19']);
        $habit->logs()->create(['logged_on' => '2026-10-20']);
        $habit->logs()->create(['logged_on' => '2026-10-21']);
        $week->forceFill(['milestone_hit_at' => now()])->save();
        $this->assertSame(GoalWeekResult::InLine, $this->fresh($week)->result()); // 7 of 7
    }

    public function test_extra_habit_days_beyond_the_target_count_as_extra_work(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek();
        $habit = $goal->habits()->create(['title' => 'Stretch', 'times_per_week' => 1]);

        foreach (['2026-10-19', '2026-10-20', '2026-10-21'] as $day) {
            $habit->logs()->create(['logged_on' => $day]);
        }

        // One habit day was planned; all three count, which is how habits help push a week past the line.
        $this->assertSame(5, $this->fresh($week)->plannedPoints());
        $this->assertEqualsWithDelta(3.0, $this->fresh($week)->earnedPoints(), 0.001);
    }

    public function test_targets_scale_with_the_length_of_the_goalweek(): void
    {
        $habit = Habit::factory()->make(['times_per_week' => 4]);

        $this->assertSame(4, $habit->targetFor(new RoadmapWeek(['starts_on' => '2026-10-19', 'ends_on' => '2026-10-25'])));
        $this->assertSame(3, $habit->targetFor(new RoadmapWeek(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-25'])));
        $this->assertSame(1, $habit->targetFor(new RoadmapWeek(['starts_on' => '2026-10-25', 'ends_on' => '2026-10-25'])));
        $this->assertSame(12, $habit->targetFor(new RoadmapWeek(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-08'])));
    }

    public function test_a_habit_can_be_logged_for_today_and_undone(): void
    {
        [$goal] = $this->goalWithCurrentWeek();
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);

        $this->actingAs($goal->user)
            ->post(route('habits.log', $habit), ['return' => 'gameplan'])
            ->assertRedirect(route('dashboard').'#gameplan');
        $this->assertTrue($habit->fresh()->isDoneOn(Carbon::parse('2026-10-21')));

        $this->actingAs($goal->user)->post(route('habits.log', $habit));
        $this->assertSame(0, $habit->logs()->count());
    }

    public function test_yesterday_can_be_logged_but_not_earlier_days_or_future_ones(): void
    {
        [$goal] = $this->goalWithCurrentWeek();
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);

        $this->actingAs($goal->user)->post(route('habits.log', $habit), ['date' => '2026-10-20'])->assertRedirect();
        $this->assertSame('2026-10-20', $habit->logs()->sole()->logged_on->toDateString());

        $this->actingAs($goal->user)->post(route('habits.log', $habit), ['date' => '2026-10-19'])->assertSessionHas('status');
        $this->actingAs($goal->user)->post(route('habits.log', $habit), ['date' => '2026-10-22'])->assertForbidden();
        $this->assertSame(1, $habit->logs()->count());
    }

    public function test_locked_goalweeks_and_other_peoples_habits_cannot_be_logged(): void
    {
        [$goal] = $this->goalWithCurrentWeek();
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        $goal->weeks()->create(['position' => 0, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'focus' => 'Before', 'milestone' => 'Done.']);

        $this->actingAs($goal->user)->post(route('habits.log', $habit), ['date' => '2026-10-14'])->assertSessionHas('status'); // Too far back.
        $this->actingAs(User::factory()->create())->post(route('habits.log', $habit))->assertForbidden();
        $this->assertSame(0, $habit->logs()->count());
    }

    public function test_habits_can_be_added_changed_and_retired_and_the_coach_reviews_each_change(): void
    {
        Queue::fake();
        [$goal] = $this->goalWithCurrentWeek();

        $this->actingAs($goal->user)
            ->post(route('habits.store', $goal), ['title' => 'Run', 'times_per_week' => 3, 'days' => ['5', '1', '3']])
            ->assertRedirect(route('goals.show', $goal).'#habits');

        $habit = $goal->habits()->sole();
        $this->assertSame([1, 3, 5], $habit->days);
        $this->assertSame('3× a week · Mon, Wed, Fri', $habit->cadence());
        Queue::assertPushed(ReviewRoadmapChanges::class);

        $this->actingAs($goal->user)->put(route('habits.update', $habit), ['title' => 'Run 3 miles', 'times_per_week' => 7]);
        $this->assertSame('Daily', $habit->fresh()->cadence());

        $this->actingAs($goal->user)->delete(route('habits.destroy', $habit));
        $this->assertNotNull($habit->fresh()->archived_at);
        $this->assertCount(3, $goal->fresh()->pending_changes);
    }

    public function test_habit_details_are_validated(): void
    {
        [$goal] = $this->goalWithCurrentWeek();

        $this->actingAs($goal->user)
            ->post(route('habits.store', $goal), ['title' => '', 'times_per_week' => 9, 'days' => ['8']])
            ->assertSessionHasErrors(['title', 'times_per_week', 'days.0']);

        $this->actingAs(User::factory()->create())
            ->post(route('habits.store', $goal), ['title' => 'Run', 'times_per_week' => 3])
            ->assertForbidden();
        $this->assertSame(0, $goal->habits()->count());
    }

    public function test_retiring_a_habit_keeps_the_weeks_it_already_counted_in(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek();
        $next = $goal->weeks()->create(['position' => 2, 'starts_on' => '2026-10-26', 'ends_on' => '2026-11-01', 'focus' => 'Next', 'milestone' => 'Done.']);
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        $habit->forceFill(['archived_at' => now()])->save();

        $this->assertTrue($habit->countsIn($week));
        $this->assertFalse($habit->countsIn($next));
    }

    public function test_the_goal_page_shows_habits_with_this_weeks_progress(): void
    {
        [$goal] = $this->goalWithCurrentWeek();
        $habit = $goal->habits()->create(['title' => 'Practice pitch', 'times_per_week' => 2]);
        $habit->logs()->create(['logged_on' => '2026-10-20']);

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('Habits')
            ->assertSee('Practice pitch')
            ->assertSee('2× a week')
            ->assertSee('1/2');
    }

    public function test_new_roadmaps_come_with_suggested_habits_unless_the_goal_already_has_some(): void
    {
        $this->travelTo('2026-10-19');
        $goal = Goal::factory()->create(['target_date' => '2026-11-01']);
        $roadmap = [
            'summary' => 'Two weeks.',
            'habits' => [['title' => 'Run', 'times_per_week' => 4]],
            'periods' => [
                ['focus' => 'Base', 'milestone' => 'Run 10 miles total.', 'tasks' => ['Buy shoes', 'Map a route', 'Sign up for a 5K']],
                ['focus' => 'Build', 'milestone' => 'Run 15 miles total.', 'tasks' => ['Try a group run']],
            ],
        ];
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('plan')->andReturn($roadmap));

        GenerateRoadmap::dispatchSync($goal);
        GenerateRoadmap::dispatchSync($goal->fresh());

        $this->assertSame(['Run'], $goal->habits()->pluck('title')->all());
        $this->assertSame(4, $goal->habits()->sole()->times_per_week);
    }

    public function test_new_roadmaps_spread_each_weeks_tasks_across_its_days(): void
    {
        $this->travelTo('2026-10-21'); // A Wednesday: the first GoalWeek runs Wednesday to Sunday.
        $goal = Goal::factory()->create(['target_date' => '2026-11-01']);
        $roadmap = [
            'summary' => 'Two weeks.',
            'habits' => [],
            'periods' => [
                ['focus' => 'Base', 'milestone' => 'Done.', 'tasks' => ['One', 'Two', 'Three']],
                ['focus' => 'Build', 'milestone' => 'Done.', 'tasks' => ['Four']],
            ],
        ];
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('plan')->andReturn($roadmap));

        GenerateRoadmap::dispatchSync($goal);

        $goal->refresh();
        $this->assertSame(['2026-10-21', '2026-10-22', '2026-10-23'], $goal->weeks[0]->tasks->map(fn ($task) => $task->planned_on->toDateString())->all());
        $this->assertSame('2026-10-26', $goal->weeks[1]->tasks[0]->planned_on->toDateString());
    }

    /**
     * @return array{Goal, RoadmapWeek}
     */
    private function goalWithCurrentWeek(): array
    {
        $goal = Goal::factory()->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'Plan.'])->save();
        $week = $goal->weeks()->create([
            'position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25',
            'focus' => 'Prototype', 'milestone' => 'Show the prototype to three customers.',
        ]);
        $week->tasks()->create(['position' => 1, 'description' => 'Build the first prototype']);
        $week->tasks()->create(['position' => 2, 'description' => 'Book three customer calls']);

        return [$goal, $week->load('tasks')];
    }

    private function fresh(RoadmapWeek $week): RoadmapWeek
    {
        return RoadmapWeek::with(['tasks', 'goal.habits.logs'])->find($week->id);
    }
}
