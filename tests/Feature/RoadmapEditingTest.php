<?php

namespace Tests\Feature;

use App\Jobs\GenerateRoadmap;
use App\Jobs\ReviewRoadmapChanges;
use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\PlanVerdict;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class RoadmapEditingTest extends TestCase
{
    use RefreshDatabase;

    private Goal $goal;

    private RoadmapWeek $pastWeek;

    private RoadmapWeek $currentWeek;

    private RoadmapWeek $nextWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->goal = Goal::factory()->create(['target_date' => today()->addDays(10)]);
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'Three GoalWeeks.'])->save();

        $this->pastWeek = $this->week(1, today()->subDays(7), 'Get started');
        $this->currentWeek = $this->week(2, today(), 'Build momentum');
        $this->nextWeek = $this->week(3, today()->addDays(7), 'Finish strong');
    }

    public function test_goalweeks_are_labelled_by_week_number(): void
    {
        $this->assertSame('GoalWeek 3', $this->nextWeek->label());
        $this->currentWeek->update(['ends_on' => today()->addDays(13)]);
        $this->assertSame('GoalWeeks 2–3', $this->currentWeek->fresh()->label());
    }

    public function test_adding_a_task_saves_it_and_queues_a_coach_review(): void
    {
        Queue::fake();

        $this->actingAs($this->goal->user)
            ->post(route('roadmap.tasks.store', $this->currentWeek), ['description' => 'Book a study room'])
            ->assertRedirect(route('goals.show', $this->goal).'#goalweek-2');

        $this->assertSame(['Do the work', 'Book a study room'], $this->currentWeek->tasks()->pluck('description')->all());
        $this->assertSame(RoadmapStatus::Pending, $this->goal->fresh()->plan_check_status);
        $this->assertSame(['Added a task to GoalWeek 2: "Book a study room"'], $this->goal->fresh()->pending_changes);
        Queue::assertPushed(ReviewRoadmapChanges::class);
    }

    public function test_rewording_and_removing_tasks_are_recorded_for_the_coach(): void
    {
        Queue::fake();
        $task = $this->currentWeek->tasks()->first();

        $this->actingAs($this->goal->user)->put(route('roadmap.tasks.update', $task), ['description' => 'Do the hard part']);
        $this->assertSame('Do the hard part', $task->fresh()->description);

        $this->actingAs($this->goal->user)->delete(route('roadmap.tasks.destroy', $task));
        $this->assertModelMissing($task);

        $this->assertSame([
            'Changed a task in GoalWeek 2 from "Do the work" to "Do the hard part"',
            'Removed a task from GoalWeek 2: "Do the hard part"',
        ], $this->goal->fresh()->pending_changes);
    }

    public function test_past_goalweeks_cannot_be_edited(): void
    {
        Queue::fake();

        $this->actingAs($this->goal->user)
            ->post(route('roadmap.tasks.store', $this->pastWeek), ['description' => 'Too late'])
            ->assertForbidden();

        $this->actingAs($this->goal->user)
            ->delete(route('roadmap.tasks.destroy', $this->pastWeek->tasks()->first()))
            ->assertForbidden();
    }

    public function test_other_people_and_retired_goals_cannot_be_edited(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('roadmap.tasks.store', $this->currentWeek), ['description' => 'Sneaky'])
            ->assertForbidden();

        $this->goal->retire();

        $this->actingAs($this->goal->user)
            ->post(route('roadmap.tasks.store', $this->currentWeek), ['description' => 'After retiring'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_coach_review_saves_its_verdict_and_clears_reviewed_changes(): void
    {
        $this->goal->forceFill(['pending_changes' => ['Removed a task from GoalWeek 2: "Do the work"']])->save();
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('review')->once()->andReturn([
            'verdict' => PlanVerdict::AtRisk,
            'note' => 'Dropping that task leaves GoalWeek 2 without practice.',
            'suggestion' => 'Add one practice set to GoalWeek 3.',
        ]));

        ReviewRoadmapChanges::dispatchSync($this->goal);

        $goal = $this->goal->fresh();
        $this->assertSame(RoadmapStatus::Ready, $goal->plan_check_status);
        $this->assertSame(PlanVerdict::AtRisk, $goal->plan_verdict);
        $this->assertNull($goal->pending_changes);

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('At risk')
            ->assertSee('Dropping that task leaves GoalWeek 2 without practice.')
            ->assertSee('Add one practice set to GoalWeek 3.');
    }

    public function test_coach_review_does_nothing_without_changes(): void
    {
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldNotReceive('review'));

        ReviewRoadmapChanges::dispatchSync($this->goal);

        $this->assertSame(RoadmapStatus::None, $this->goal->fresh()->plan_check_status);
    }

    public function test_replanning_starts_from_the_current_goalweek(): void
    {
        Queue::fake();

        $this->actingAs($this->goal->user)
            ->post(route('goals.roadmap.store', $this->goal))
            ->assertRedirect(route('goals.show', $this->goal));

        Queue::assertPushed(GenerateRoadmap::class, fn (GenerateRoadmap $job) => $job->fromPosition === 2);
    }

    public function test_replanning_keeps_past_goalweeks_and_rebuilds_the_rest(): void
    {
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('plan')
            ->withArgs(fn (Goal $goal, array $periods, $pastWeeks) => count($periods) === 2 && collect($pastWeeks)->pluck('position')->all() === [1])
            ->andReturn([
                'summary' => 'Back on track.',
                'periods' => [
                    ['focus' => 'Catch up', 'milestone' => 'Close the gap.', 'tasks' => ['Redo the practice set']],
                    ['focus' => 'Finish strong', 'milestone' => 'Done.', 'tasks' => ['Take the exam']],
                ],
            ]));

        GenerateRoadmap::dispatchSync($this->goal, 2);

        $this->assertSame(['Get started', 'Catch up', 'Finish strong'], $this->goal->weeks()->pluck('focus')->all());
        $this->assertTrue($this->goal->weeks()->where('position', 2)->first()->starts_on->isSameDay(today()));
        $this->assertSame('Back on track.', $this->goal->fresh()->roadmap_summary);
    }

    private function week(int $position, $startsOn, string $focus): RoadmapWeek
    {
        $week = $this->goal->weeks()->create([
            'position' => $position,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(6),
            'focus' => $focus,
            'milestone' => 'Hit the mark.',
        ]);
        $week->tasks()->create(['position' => 1, 'description' => 'Do the work']);

        return $week;
    }
}
