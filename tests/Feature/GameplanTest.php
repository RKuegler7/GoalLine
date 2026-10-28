<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameplanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday morning.
    }

    public function test_todays_gameplan_lists_todays_tasks_carried_over_tasks_and_habits_due_today(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek([
            'Build the first prototype' => '2026-10-21',
            'Book three customer calls' => '2026-10-20',
            'Write the pitch' => '2026-10-23',
        ]);
        $goal->habits()->create(['title' => 'Cold outreach', 'times_per_week' => 3]);
        $goal->habits()->create(['title' => 'Weekend review', 'times_per_week' => 1, 'days' => [7]]);

        $page = $this->actingAs($goal->user)->get(route('dashboard'))->getContent();
        $gameplan = Str::betweenFirst($page, 'id="gameplan"', '</section>');

        $this->assertSame(1, preg_match('/Book three customer calls.*Carried over from Tuesday.*Build the first prototype.*Cold outreach/s', $gameplan));
        $this->assertStringContainsString('0 of 3 done', $gameplan);
        $this->assertStringContainsString('Plan my week', $gameplan);
        $this->assertStringNotContainsString('Write the pitch', $gameplan);
        $this->assertStringNotContainsString('Weekend review', $gameplan);
    }

    public function test_finishing_everything_on_todays_gameplan_wins_the_day(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek(['Build the first prototype' => '2026-10-21']);
        $habit = $goal->habits()->create(['title' => 'Cold outreach', 'times_per_week' => 3]);

        $this->actingAs($goal->user)
            ->post(route('roadmap.tasks.check', $week->tasks[0]), ['return' => 'gameplan'])
            ->assertRedirect(route('dashboard').'#gameplan');
        $this->actingAs($goal->user)->post(route('habits.log', $habit), ['return' => 'gameplan']);

        $this->actingAs($goal->user)->get(route('dashboard'))->assertSee('All 2 done. Day won.');
    }

    public function test_a_task_can_be_moved_to_tomorrow_but_not_past_the_end_of_its_goalweek(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek(['Build the first prototype' => '2026-10-21']);
        $task = $week->tasks[0];

        $this->actingAs($goal->user)
            ->post(route('roadmap.tasks.tomorrow', $task))
            ->assertRedirect(route('dashboard').'#gameplan');
        $this->assertSame('2026-10-22', $task->fresh()->planned_on->toDateString());

        $this->travelTo(Carbon::parse('2026-10-25 09:00', config('app.timezone'))); // Sunday, the last day.
        $this->actingAs($goal->user)->post(route('roadmap.tasks.tomorrow', $task));
        $this->assertSame('2026-10-22', $task->fresh()->planned_on->toDateString());

        $this->actingAs(User::factory()->create())->post(route('roadmap.tasks.tomorrow', $task))->assertForbidden();
    }

    public function test_the_planner_shows_the_week_and_saves_a_day_for_each_task(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek(['Build the first prototype' => null, 'Book three customer calls' => null]);
        [$first, $second] = $week->tasks;

        $this->actingAs($goal->user)
            ->get(route('planner.show'))
            ->assertOk()
            ->assertSee('Plan my week')
            ->assertSee('Launch the business')
            ->assertSee('Plan the rest for me');

        $this->actingAs($goal->user)
            ->put(route('planner.update'), ['tasks' => [$first->id => '2026-10-24', $second->id => '']])
            ->assertRedirect(route('planner.show'));

        $this->assertSame('2026-10-24', $first->fresh()->planned_on->toDateString());
        $this->assertNull($second->fresh()->planned_on);
    }

    public function test_the_planner_rejects_days_outside_the_goalweek_and_other_peoples_tasks(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek(['Build the first prototype' => null]);
        $task = $week->tasks[0];

        $this->actingAs($goal->user)
            ->put(route('planner.update'), ['tasks' => [$task->id => '2026-10-27']])
            ->assertSessionHasErrors('tasks.'.$task->id);

        $this->actingAs(User::factory()->create())
            ->put(route('planner.update'), ['tasks' => [$task->id => '2026-10-22']])
            ->assertForbidden();

        $this->assertNull($task->fresh()->planned_on);
    }

    public function test_auto_plan_spreads_unscheduled_tasks_over_the_rest_of_the_week(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek([
            'Already Thursday' => '2026-10-22',
            'One' => null,
            'Two' => null,
            'Three' => null,
        ]);

        $this->actingAs($goal->user)
            ->post(route('planner.auto'))
            ->assertRedirect(route('planner.show'))
            ->assertSessionHas('status', 'Planned 3 tasks across the rest of your week.');

        $days = $week->tasks()->orderBy('position')->get()->map(fn ($task) => $task->planned_on->toDateString())->all();
        $this->assertSame(['2026-10-22', '2026-10-21', '2026-10-23', '2026-10-24'], $days);
    }

    public function test_personal_tasks_added_mid_week_land_on_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00', config('app.timezone'))); // Tuesday, the last day to add one.
        [$goal, $week] = $this->goalWithCurrentWeek(['Build the first prototype' => '2026-10-21']);
        $week->tasks->first()->forceFill(['points' => 20])->save();

        $this->actingAs($goal->user)->post(route('roadmap.tasks.store', $week), ['description' => 'Demo at the meetup']);

        $personal = $week->tasks()->where('is_personal', true)->sole();
        $this->assertSame('2026-10-20', $personal->planned_on->toDateString());
    }

    public function test_habit_check_ins_show_in_week_at_a_glance(): void
    {
        [$goal] = $this->goalWithCurrentWeek(['Build the first prototype' => '2026-10-21']);
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        $habit->logs()->create(['logged_on' => '2026-10-19']);
        $habit->logs()->create(['logged_on' => '2026-10-21']);

        $this->actingAs($goal->user)
            ->get(route('dashboard'))
            ->assertSee('2 check-ins')
            ->assertSee('3× a week');
    }

    /**
     * @param  array<string, string|null>  $tasks  Description => planned day.
     * @return array{Goal, RoadmapWeek}
     */
    private function goalWithCurrentWeek(array $tasks): array
    {
        $goal = Goal::factory()->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'Plan.'])->save();
        $week = $goal->weeks()->create([
            'position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25',
            'focus' => 'Prototype', 'milestone' => 'Show the prototype to three customers.',
        ]);

        $position = 0;
        foreach ($tasks as $description => $day) {
            $week->tasks()->create(['position' => ++$position, 'description' => $description, 'planned_on' => $day]);
        }

        return [$goal, $week->load('tasks')];
    }
}
