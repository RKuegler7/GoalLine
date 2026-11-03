<?php

namespace Tests\Feature;

use App\Exceptions\RoadmapPlanningException;
use App\GoalCategory;
use App\Jobs\GenerateRoadmap;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\PlanVersion;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\FallbackPlan;
use App\Services\IntakeQuestions;
use App\Services\PlanEditor;
use App\Services\PlanFloor;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class PlanHonestyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00', config('app.timezone'))); // A Wednesday.
    }

    public function test_the_floor_follows_documented_formulas_per_category(): void
    {
        $exam = Goal::factory()->make(['category' => GoalCategory::Academic, 'title' => 'Pass the CPA FAR exam']);
        $this->assertSame(6, PlanFloor::forStretch($exam, 2, 8, 8, 7), 'Academic base 6, 8-week plan ×1.0.');
        $this->assertSame(5, PlanFloor::forStretch($exam, 7, 8, 8, 7), 'Exam week tapers ×0.8.');
        $this->assertSame(3, PlanFloor::forStretch($exam, 0, 8, 8, 3), 'A 3-day first week gets 3/7 of the floor.');

        $race = Goal::factory()->make(['category' => GoalCategory::Fitness, 'title' => 'Run a half marathon']);
        $this->assertSame(4, PlanFloor::forStretch($race, 0, 10, 10, 7), 'Fitness starts gently at ×0.8.');
        $this->assertSame(5, PlanFloor::forStretch($race, 8, 10, 10, 7), 'and builds toward ×1.1 (5.3 rounds to 5).');

        $habit = Goal::factory()->make(['category' => GoalCategory::Personal, 'title' => 'Learn Spanish basics']);
        $this->assertSame(5, PlanFloor::forStretch($habit, 1, 3, 3, 7), 'A plan of 4 weeks or less ×1.25.');
        $this->assertSame(4, PlanFloor::forStretch($habit, 1, 20, 20, 7), 'Long plans ×0.9 (3.6 rounds to 4).');
    }

    public function test_intake_answers_cap_the_floor_and_soften_it_for_injuries(): void
    {
        $goal = Goal::factory()->create(['category' => GoalCategory::Fitness, 'title' => 'Run a half marathon']);
        $intake = GoalIntake::factory()->for($goal)->create();
        $intake->answer(IntakeQuestions::answers($intake->questions, [
            'experience' => ['value' => 'Competitive'],
            'health' => ['value' => 'Sore left knee'],
        ]));

        // 5 × 1.1 (builds) × 1.15 (competitive) × 0.85 (injury) = 5.4, rounds to 5.
        $this->assertSame(5, PlanFloor::forStretch($goal->fresh(), 8, 10, 10, 7));

        $study = Goal::factory()->create(['category' => GoalCategory::Academic, 'title' => 'Pass the bar']);
        $studyIntake = GoalIntake::factory()->for($study)->create(['questions' => IntakeQuestions::fallback(GoalCategory::Academic, false)]);
        $studyIntake->answer(IntakeQuestions::answers($studyIntake->questions, ['hours' => ['value' => 4]]));

        $this->assertSame(3, PlanFloor::forStretch($study->fresh(), 2, 8, 8, 7), '4 hours a week caps the floor at 80% of that (3).');
    }

    public function test_a_plan_below_its_floor_is_raised_by_code_with_a_note(): void
    {
        $goal = Goal::factory()->create(['category' => GoalCategory::Academic, 'title' => 'Pass the CPA FAR exam', 'target_date' => '2026-11-22']);
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('plan')->andReturn([
            'summary' => 'Easy does it.',
            'habits' => [],
            'periods' => array_fill(0, 3, ['focus' => 'Coast', 'milestone' => 'Read a page.', 'tasks' => ['Read one page']]),
        ]));

        (new GenerateRoadmap($goal))->handle(app(RoadmapPlanner::class));

        foreach ($goal->fresh()->weeks()->with('tasks')->get() as $week) {
            $this->assertNotNull($week->floor_points);
            $this->assertGreaterThanOrEqual($week->floor_points, $week->load('goal.habits.logs')->plannedPoints());
            $this->assertTrue($week->tasks->contains('is_floor', true));
            $this->assertStringStartsWith('We raised this week to the minimum pace your deadline needs', $week->floor_note);
        }

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('We raised this week to the minimum pace your deadline needs')
            ->assertSee('Minimum pace')->assertSee('Draft plan: look it over');
    }

    public function test_refining_or_editing_can_never_go_below_the_floor(): void
    {
        [$goal, $week] = $this->goalWithUpcomingWeek(floor: 6, tasks: 4); // 4 tasks + the milestone = 6 planned.

        $editor = app(PlanEditor::class);
        $proposal = [['position' => $week->position, 'focus' => 'Lighter', 'milestone' => 'Done', 'tasks' => ['Only this']]];
        $diff = $editor->diff($goal->fresh('weeks.tasks', 'habits.logs'), $proposal);
        $this->assertTrue($diff['weeks'][0]['raised']);
        $this->assertSame(6, $diff['weeks'][0]['pointsAfter']);

        $editor->apply($goal, $proposal, PlanVersion::REFINED, 'Make it easy');
        $this->assertSame(6, $week->fresh()->load('tasks', 'goal.habits.logs')->plannedPoints());

        // By hand: removing a task that would dip below the floor is turned down.
        $task = $week->tasks()->where('is_floor', false)->first();
        $this->actingAs($goal->user)->delete(route('roadmap.tasks.destroy', $task))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'below the minimum pace your deadline needs (6 points)'));
        $this->assertModelExists($task);
    }

    public function test_lighter_weeks_need_a_reason_are_capped_at_two_and_keep_70_percent(): void
    {
        [$goal, $week] = $this->goalWithUpcomingWeek(floor: 10, tasks: 8);
        $others = collect(range(1, 2))->map(fn (int $offset) => $goal->weeks()->create(['position' => $week->position + $offset, 'starts_on' => $week->starts_on->copy()->addWeeks($offset), 'ends_on' => $week->ends_on->copy()->addWeeks($offset), 'focus' => 'Later', 'milestone' => 'Done', 'floor_points' => 10]));
        $others->each(fn (RoadmapWeek $other) => $other->forceFill(['floor_points' => 10])->save());

        $this->actingAs($goal->user)->post(route('goalweeks.lighter', $week), ['reason' => ''])->assertSessionHasErrorsIn("lighter{$week->id}", 'reason');

        $this->actingAs($goal->user)->post(route('goalweeks.lighter', $week), ['reason' => 'Finals week']);
        $this->assertSame(7, $week->fresh()->effectiveFloor());
        $this->assertSame(RoadmapWeek::LIGHTER, $week->fresh()->load('tasks', 'goal.habits.logs')->planStatus());
        $this->assertStringContainsString('Lighter week declared for', $goal->planVersions()->latest('id')->first()->summary);

        $this->actingAs($goal->user)->post(route('goalweeks.lighter', $others[0]), ['reason' => 'Travel']);
        $this->actingAs($goal->user)->post(route('goalweeks.lighter', $others[1]), ['reason' => 'One more'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'both lighter weeks'));
        $this->assertFalse($others[1]->fresh()->isLighter());

        // Undo before it starts; and never once a week is underway.
        $this->actingAs($goal->user)->delete(route('goalweeks.steady', $week));
        $this->assertFalse($week->fresh()->isLighter());
        $current = $goal->weeks()->where('position', 1)->sole();
        $this->actingAs($goal->user)->post(route('goalweeks.lighter', $current), ['reason' => 'Too late'])->assertForbidden();
    }

    public function test_a_habit_target_cant_be_lowered_once_the_week_is_locked(): void
    {
        [$goal] = $this->goalWithUpcomingWeek(floor: 4, tasks: 2);
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 4]);
        $habit->forceFill(['created_at' => '2026-10-20'])->save();

        $this->actingAs($goal->user)->put(route('habits.update', $habit), ['title' => 'Run', 'cadence' => 'weekly', 'times_per_week' => 2])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'plan is locked'));
        $this->assertSame(4, $habit->fresh()->times_per_week);

        $this->actingAs($goal->user)->put(route('habits.update', $habit), ['title' => 'Run', 'cadence' => 'weekly', 'times_per_week' => 5]);
        $this->assertSame(5, $habit->fresh()->times_per_week, 'Raising the target is always fine.');
    }

    public function test_league_mates_see_plan_status_and_history_but_not_private_notes(): void
    {
        [$goal, $week] = $this->goalWithUpcomingWeek(floor: 4, tasks: 4);
        $goal->update(['why' => 'Private reason nobody else should see']);
        $week->forceFill(['lighter_at' => now(), 'lighter_reason' => 'Finals week'])->save();
        PlanVersion::record($goal, PlanVersion::EDITED, 'Lighter week declared for GoalWeek 2: Finals week');

        $league = League::factory()->create(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-29']);
        $member = $league->members()->create(['user_id' => $goal->user_id, 'goal_id' => $goal->id]);
        $mate = User::factory()->create();
        $league->members()->create(['user_id' => $mate->id]);

        $this->actingAs($mate)->get(route('leagues.members.plan', [$league, $member]))->assertOk()
            ->assertSee('Lighter week')->assertSee('Finals week')
            ->assertSee('Plan history')->assertDontSee('Private reason nobody else should see');

        $this->actingAs(User::factory()->create())->get(route('leagues.members.plan', [$league, $member]))->assertForbidden();
    }

    public function test_when_the_coach_fails_a_starter_plan_at_the_floor_is_saved_and_said_plainly(): void
    {
        $goal = Goal::factory()->create(['category' => GoalCategory::Fitness, 'title' => 'Run a 10K', 'target_date' => '2026-11-29']);
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('plan')->andThrow(new RoadmapPlanningException('Invalid JSON.')));

        (new GenerateRoadmap($goal))->handle(app(RoadmapPlanner::class));

        $goal->refresh();
        $this->assertSame(RoadmapStatus::Ready, $goal->roadmap_status);
        $this->assertSame('fallback', $goal->plan_source);
        $this->assertSame(FallbackPlan::SUMMARY, $goal->roadmap_summary);
        $goal->weeks()->with('tasks')->get()->each(fn (RoadmapWeek $week) => $this->assertGreaterThanOrEqual($week->floor_points, $week->load('goal.habits.logs')->plannedPoints()));

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('The coach was unavailable')->assertSee('Try the coach again')->assertDontSee('Invalid JSON');

        $this->actingAs($goal->user)->post(route('goals.plan.accept', $goal))->assertSessionHas('status');
        $this->assertNotNull($goal->fresh()->plan_accepted_at);
        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertDontSee('Draft plan: look it over');
    }

    public function test_the_backfill_command_gives_older_plans_their_floor(): void
    {
        [$goal, $week] = $this->goalWithUpcomingWeek(floor: null, tasks: 1);

        $this->artisan('goalline:plan-floors')->assertSuccessful();

        $week->refresh();
        $this->assertNotNull($week->floor_points);
        $this->assertGreaterThanOrEqual($week->floor_points, $week->load('tasks', 'goal.habits.logs')->plannedPoints());
        $this->assertNotNull($goal->weeks()->where('position', 1)->sole()->floor_points, 'This week gets a floor too, for its label.');
    }

    /**
     * A goal with this GoalWeek (underway) and the next one, which has the given floor and plain 1-point tasks.
     *
     * @return array{0: Goal, 1: RoadmapWeek}
     */
    private function goalWithUpcomingWeek(?int $floor, int $tasks): array
    {
        $goal = Goal::factory()->create(['category' => GoalCategory::Academic, 'title' => 'Pass the CPA FAR exam', 'target_date' => '2026-12-27']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'plan_accepted_at' => now()])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'Now', 'milestone' => 'Done']);
        $week = $goal->weeks()->create(['position' => 2, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-15', 'focus' => 'Next', 'milestone' => 'Done']);
        $week->forceFill(['floor_points' => $floor])->save();

        foreach (range(1, $tasks) as $position) {
            $week->tasks()->create(['position' => $position, 'description' => "Study block {$position}"]);
        }

        return [$goal, $week];
    }
}
