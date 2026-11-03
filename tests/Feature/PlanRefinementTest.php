<?php

namespace Tests\Feature;

use App\Exceptions\RoadmapPlanningException;
use App\GoalWeekResult;
use App\Jobs\RefinePlan;
use App\Models\Goal;
use App\Models\PlanRefinement;
use App\Models\PlanVersion;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\PlanEditor;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class PlanRefinementTest extends TestCase
{
    use RefreshDatabase;

    private Goal $goal;

    /**
     * @var array<int, RoadmapWeek>
     */
    private array $weeks;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00', config('app.timezone'))); // A Wednesday.
        $this->goal = Goal::factory()->create(['target_date' => '2026-11-22']);
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'Four GoalWeeks.'])->save();

        // Last week (all done), this week (one check-in), and two weeks ahead.
        foreach ([1 => '2026-10-26', 2 => '2026-11-02', 3 => '2026-11-09', 4 => '2026-11-16'] as $position => $monday) {
            $week = $this->goal->weeks()->create(['position' => $position, 'starts_on' => $monday, 'ends_on' => Carbon::parse($monday)->addDays(6), 'focus' => "Focus {$position}", 'milestone' => "Milestone {$position}"]);
            foreach (['Read chapter', 'Practice set', 'Review notes'] as $index => $task) {
                $week->tasks()->create(['position' => $index + 1, 'description' => "{$task} {$position}", 'planned_on' => $monday]);
            }
            $this->weeks[$position] = $week;
        }

        $this->weeks[1]->tasks->each(fn ($task) => $task->forceFill(['completed_at' => '2026-10-27 09:00'])->save());
        $this->weeks[1]->forceFill(['milestone_hit_at' => '2026-10-31 09:00'])->save();
        $this->weeks[2]->tasks->first()->forceFill(['completed_at' => '2026-11-03 09:00'])->save();
        $this->weeks[3]->tasks()->create(['position' => 4, 'description' => 'Stretch 10 minutes'])->forceFill(['is_personal' => true, 'points' => 1])->save();
    }

    public function test_only_goalweeks_that_havent_started_can_change(): void
    {
        $editor = app(PlanEditor::class);

        $this->assertSame([3, 4], $editor->editableWeeks($this->goal->fresh('weeks.tasks', 'habits.logs'))->pluck('position')->all());

        // Plan lock: this week stays locked even with nothing checked off.
        $this->weeks[2]->tasks()->update(['completed_at' => null]);
        $this->assertSame([3, 4], $editor->editableWeeks($this->goal->fresh('weeks.tasks', 'habits.logs'))->pluck('position')->all());
    }

    public function test_a_request_goes_to_the_coach_and_nothing_changes_until_the_preview(): void
    {
        $this->actingAs($this->goal->user)->get(route('goals.show', $this->goal))
            ->assertSee('Refine your plan')->assertSee('Make weeks lighter, I have finals')->assertSee('Changes 2 upcoming GoalWeeks at most');

        $this->actingAs($this->goal->user)->post(route('goals.refinements.store', $this->goal), ['instruction' => ''])
            ->assertSessionHasErrorsIn('refine', 'instruction');

        $response = $this->actingAs($this->goal->user)->post(route('goals.refinements.store', $this->goal), ['instruction' => 'I have finals in GoalWeek 3, make it lighter.']);
        $refinement = PlanRefinement::sole();
        $response->assertRedirect(route('goals.refinements.show', [$this->goal, $refinement]));
        Queue::assertPushed(RefinePlan::class);

        $this->actingAs($this->goal->user)->get(route('goals.refinements.show', [$this->goal, $refinement]))
            ->assertOk()->assertSee('Working on it')->assertSee('I have finals in GoalWeek 3, make it lighter.');
        $this->assertSame(['Read chapter 3', 'Practice set 3', 'Review notes 3', 'Stretch 10 minutes'], $this->weeks[3]->tasks()->pluck('description')->all());
    }

    public function test_the_preview_shows_tasks_added_removed_and_moved_with_points(): void
    {
        $refinement = $this->readyRefinement();

        // GoalWeek 3 trades the practice set for a light review, so its planned points stay at 6.
        $this->actingAs($this->goal->user)->get(route('goals.refinements.show', [$this->goal, $refinement]))
            ->assertOk()
            ->assertSee('Why:')->assertSee('Finals week is lighter; the practice set moved to the week after.')
            ->assertSeeInOrder(['GoalWeek 3', 'Planned points 6', '6', 'Light review session', 'added'])
            ->assertDontSee('removed')
            ->assertSeeInOrder(['GoalWeek 4', 'Planned points 5', '6', '(+1)', 'Focus:', 'Focus 4', 'Finish strong', 'Practice set 3', 'moved from GoalWeek 3'])
            ->assertSee('Accept and update my plan')->assertSee('Discard');

        $diff = app(PlanEditor::class)->diff($this->goal->fresh('weeks.tasks', 'habits.logs'), $refinement->proposal);
        $this->assertSame(['Light review session'], $diff['weeks'][0]['added']);
        $this->assertSame([], $diff['weeks'][0]['removed'], 'A task moved to another week is a move, not a removal.');
        $this->assertSame([['description' => 'Practice set 3', 'from' => 'GoalWeek 3']], $diff['weeks'][1]['moved']);
        $this->assertSame(6, $diff['weeks'][0]['pointsBefore']);
    }

    public function test_accepting_changes_only_upcoming_weeks_and_keeps_personal_tasks_and_past_scores(): void
    {
        $refinement = $this->readyRefinement();
        $pastResult = $this->weeks[1]->fresh('tasks')->result();
        $currentTasks = $this->weeks[2]->tasks()->pluck('description', 'id')->all();

        $this->actingAs($this->goal->user)->post(route('goals.refinements.accept', [$this->goal, $refinement]))
            ->assertRedirect(route('goals.show', $this->goal))->assertSessionHas('status', 'Plan updated: 2 GoalWeeks changed. Past check-ins stay as they are.');

        $this->assertSame(['Read chapter 3', 'Light review session', 'Review notes 3', 'Stretch 10 minutes'], $this->weeks[3]->tasks()->orderBy('position')->pluck('description')->all());
        $this->assertSame(['Read chapter 4', 'Practice set 4', 'Practice set 3', 'Review notes 4'], $this->weeks[4]->tasks()->orderBy('position')->pluck('description')->all());
        $this->assertSame('Finish strong', $this->weeks[4]->fresh()->focus);
        $this->assertTrue($this->weeks[3]->tasks()->whereNull('planned_on')->doesntExist(), 'New tasks get a day.');

        $this->assertSame($currentTasks, $this->weeks[2]->tasks()->pluck('description', 'id')->all());
        $this->assertSame($pastResult, $this->weeks[1]->fresh('tasks')->result());
        $this->assertSame(GoalWeekResult::InLine, $pastResult);

        $this->assertSame(PlanRefinement::ACCEPTED, $refinement->fresh()->status);
        $version = $this->goal->planVersions()->latest('id')->first();
        $this->assertSame(PlanVersion::REFINED, $version->kind);
        $this->assertStringContainsString('I have finals', $version->summary);
    }

    public function test_weeks_that_started_since_the_preview_are_left_alone(): void
    {
        $refinement = $this->readyRefinement();
        $this->travelTo(Carbon::parse('2026-11-10 10:00', config('app.timezone'))); // GoalWeek 3 is now underway, with a check-in.
        $this->weeks[3]->tasks()->first()->forceFill(['completed_at' => now()])->save();

        $this->actingAs($this->goal->user)->get(route('goals.refinements.show', [$this->goal, $refinement]))->assertSee('started since it was made');
        $this->actingAs($this->goal->user)->post(route('goals.refinements.accept', [$this->goal, $refinement]));

        $this->assertSame(['Read chapter 3', 'Practice set 3', 'Review notes 3', 'Stretch 10 minutes'], $this->weeks[3]->tasks()->orderBy('position')->pluck('description')->all());
        $this->assertSame('Finish strong', $this->weeks[4]->fresh()->focus);
    }

    public function test_discarding_leaves_the_plan_unchanged(): void
    {
        $refinement = $this->readyRefinement();

        $this->actingAs($this->goal->user)->post(route('goals.refinements.discard', [$this->goal, $refinement]))
            ->assertSessionHas('status', 'Discarded. Your plan is unchanged.');

        $this->assertSame(PlanRefinement::DISCARDED, $refinement->fresh()->status);
        $this->assertSame('Focus 4', $this->weeks[4]->fresh()->focus);
        $this->assertSame(0, $this->goal->planVersions()->count());
        $this->actingAs($this->goal->user)->post(route('goals.refinements.accept', [$this->goal, $refinement]))->assertNotFound();
    }

    public function test_the_coachs_answer_is_saved_for_preview_and_a_failure_is_friendly(): void
    {
        $refinement = PlanRefinement::factory()->for($this->goal)->create();
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('refine')->andReturn([
            'explanation' => 'Lighter finals week.',
            'weeks' => [['position' => 3, 'focus' => 'Light week', 'milestone' => 'Stay fresh', 'tasks' => ['Light review session']]],
        ]));

        (new RefinePlan($refinement))->handle(app(RoadmapPlanner::class), app(PlanEditor::class));
        $this->assertSame(PlanRefinement::READY, $refinement->fresh()->status);

        $failing = PlanRefinement::factory()->for($this->goal)->create();
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('refine')->andThrow(new RoadmapPlanningException('Malformed.')));
        (new RefinePlan($failing))->handle(app(RoadmapPlanner::class), app(PlanEditor::class));

        $this->assertSame(PlanRefinement::FAILED, $failing->fresh()->status);
        $this->actingAs($this->goal->user)->get(route('goals.refinements.show', [$this->goal, $failing]))
            ->assertSee("That change didn't come through", false)->assertDontSee('Malformed');
    }

    public function test_malformed_coach_output_is_rejected_or_trimmed(): void
    {
        $week = fn (array $overrides = []) => ['stretch' => 3, 'focus' => 'Light week', 'milestone' => 'Stay fresh', 'tasks' => ['Review'], ...$overrides];

        $refined = RoadmapPlanner::validatedRefinement(['explanation' => '<b>Lighter</b> week.', 'weeks' => [
            $week(['tasks' => [' <i>Review</i> ', '', ...array_fill(0, 10, 'Extra')]]),
            $week(['stretch' => 1, 'focus' => 'Rewrite the past']), // Not allowed to change: dropped.
            $week(['stretch' => 3, 'focus' => 'Duplicate']),          // Second copy: dropped.
        ]], [3, 4]);

        $this->assertSame('Lighter week.', $refined['explanation']);
        $this->assertCount(1, $refined['weeks']);
        $this->assertSame('Review', $refined['weeks'][0]['tasks'][0]);
        $this->assertCount(RoadmapPlanner::MAX_REFINED_TASKS, $refined['weeks'][0]['tasks']);

        foreach ([null, ['weeks' => []], ['explanation' => 'Why', 'weeks' => [$week(['stretch' => 1])]], ['explanation' => 'Why', 'weeks' => [$week(['tasks' => []])]], ['explanation' => '', 'weeks' => [$week()]]] as $bad) {
            try {
                RoadmapPlanner::validatedRefinement($bad, [3, 4]);
                $this->fail('Malformed output should be rejected.');
            } catch (RoadmapPlanningException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_brief_marks_what_can_change_and_fixed_tasks(): void
    {
        $planner = $this->partialMock(RoadmapPlanner::class);
        $goal = $this->goal->fresh('weeks.tasks', 'habits.logs');

        $brief = $planner->refineBrief($goal, 'Make week 3 lighter', app(PlanEditor::class)->editableWeeks($goal));

        $this->assertStringContainsString('#1 GoalWeek 1 (Oct 26 to Nov 1, past)', $brief);
        $this->assertStringContainsString('#2 GoalWeek 2 (Nov 2 to Nov 8, locked: already underway)', $brief);
        $this->assertStringContainsString('#3 GoalWeek 3 (Nov 9 to Nov 15, can change)', $brief);
        $this->assertStringContainsString('Stretch 10 minutes (personal, fixed)', $brief);
        $this->assertStringContainsString('Stretches that can change: #3, #4', $brief);
        $this->assertStringContainsString("<request>\nMake week 3 lighter\n</request>", $brief);
    }

    public function test_manual_edits_are_logged_and_an_earlier_version_can_be_restored(): void
    {
        $user = $this->goal->user;
        PlanVersion::record($this->goal, PlanVersion::BUILT, 'The coach built the plan');
        $task = $this->weeks[3]->tasks()->where('description', 'Practice set 3')->sole();

        $this->actingAs($user)->put(route('goalweeks.update', $this->weeks[3]), ['focus' => 'Finals week', 'milestone' => 'Sit every final']);
        $this->actingAs($user)->post(route('roadmap.tasks.move', $task), ['week_id' => $this->weeks[4]->id]);
        $this->actingAs($user)->post(route('roadmap.tasks.move', $this->weeks[4]->tasks()->where('description', 'Review notes 4')->sole()), ['direction' => 'up']);

        $this->assertSame('Finals week', $this->weeks[3]->fresh()->focus);
        $this->assertSame($this->weeks[4]->id, $task->fresh()->roadmap_week_id);
        $this->assertSame(['Read chapter 4', 'Review notes 4', 'Practice set 4', 'Practice set 3'], $this->weeks[4]->tasks()->orderBy('position')->pluck('description')->all());

        // The three edits share one history entry; the built plan is still there to go back to.
        $this->assertSame(2, $this->goal->planVersions()->count());
        $edits = $this->goal->planVersions()->latest('id')->first();
        $this->assertSame(PlanVersion::EDITED, $edits->kind);
        $this->assertStringContainsString('Moved "Practice set 3" from GoalWeek 3 to GoalWeek 4', $edits->summary);

        $this->actingAs($user)->get(route('goals.show', $this->goal))->assertSee('Plan history')->assertSee('Your edits')->assertSee('Preview restoring');

        $built = $this->goal->planVersions()->where('kind', PlanVersion::BUILT)->sole();
        $this->actingAs($user)->post(route('goals.plan-versions.restore', [$this->goal, $built]));
        $restore = PlanRefinement::where('kind', PlanRefinement::RESTORE)->sole();
        $this->actingAs($user)->get(route('goals.refinements.show', [$this->goal, $restore]))->assertSee('Plan history')->assertSee('Finals week');
        $this->actingAs($user)->post(route('goals.refinements.accept', [$this->goal, $restore]));

        $this->assertSame('Focus 3', $this->weeks[3]->fresh()->focus);
        $this->assertSame(['Read chapter 3', 'Practice set 3', 'Review notes 3', 'Stretch 10 minutes'], $this->weeks[3]->tasks()->orderBy('position')->pluck('description')->all());
        $this->assertSame(PlanVersion::RESTORED, $this->goal->planVersions()->latest('id')->first()->kind);
    }

    public function test_weeks_underway_cant_be_rewritten_by_hand(): void
    {
        $user = $this->goal->user;
        $current = $this->weeks[2]->tasks()->whereNull('completed_at')->first();

        $this->actingAs($user)->put(route('goalweeks.update', $this->weeks[2]), ['focus' => 'Easier', 'milestone' => 'Anything'])->assertForbidden();
        $this->actingAs($user)->post(route('roadmap.tasks.move', $current), ['week_id' => $this->weeks[3]->id])->assertForbidden();
        $this->actingAs($user)->post(route('roadmap.tasks.move', $this->weeks[3]->tasks()->where('is_personal', true)->sole()), ['week_id' => $this->weeks[4]->id])->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('goalweeks.update', $this->weeks[3]), ['focus' => 'Mine', 'milestone' => 'Mine'])->assertForbidden();

        $this->assertSame('Milestone 2', $this->weeks[2]->fresh()->milestone);
    }

    private function readyRefinement(): PlanRefinement
    {
        $refinement = PlanRefinement::factory()->for($this->goal)->create(['instruction' => 'I have finals in GoalWeek 3, make it lighter.']);
        $refinement->forceFill([
            'status' => PlanRefinement::READY,
            'explanation' => 'Finals week is lighter; the practice set moved to the week after.',
            'proposal' => [
                ['position' => 3, 'focus' => 'Focus 3', 'milestone' => 'Milestone 3', 'tasks' => ['Read chapter 3', 'Light review session', 'Review notes 3']],
                ['position' => 4, 'focus' => 'Finish strong', 'milestone' => 'Milestone 4', 'tasks' => ['Read chapter 4', 'Practice set 4', 'Practice set 3', 'Review notes 4']],
            ],
        ])->save();

        return $refinement;
    }
}
