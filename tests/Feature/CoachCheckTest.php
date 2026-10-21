<?php

namespace Tests\Feature;

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

class CoachCheckTest extends TestCase
{
    use RefreshDatabase;

    private Goal $goal;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->goal = Goal::factory()->create(['target_date' => today()->addDays(20)]);
        $this->goal->forceFill([
            'roadmap_status' => RoadmapStatus::Ready,
            'roadmap_summary' => 'Build it, then launch it.',
            'plan_check_status' => RoadmapStatus::Ready,
            'plan_verdict' => PlanVerdict::AtRisk,
            'plan_note' => 'Filing for a trademark crowds GoalWeek 1.',
            'plan_suggestion' => 'Move the trademark filing to GoalWeek 2.',
        ])->save();

        $this->week = $this->goal->weeks()->create([
            'position' => 1, 'starts_on' => today(), 'ends_on' => today()->addDays(6),
            'focus' => 'Validate', 'milestone' => 'Talk to ten customers.',
        ]);
        $this->week->tasks()->create(['position' => 1, 'description' => 'File for trademark']);
    }

    public function test_at_risk_check_shows_recommendation_fix_and_keep_options(): void
    {
        $this->withFix(['action' => 'remove_task', 'stretch' => 1, 'task' => 'File for trademark', 'new_text' => '']);

        $this->actingAs($this->goal->user)
            ->get(route('goals.show', $this->goal))
            ->assertSee("Coach's recommendation:")
            ->assertSee('Move the trademark filing to GoalWeek 2.')
            ->assertSee('Apply fix')
            ->assertSee('Remove “File for trademark” from GoalWeek 1', false)
            ->assertSee('Keep my plan');
    }

    public function test_applying_a_remove_fix_edits_the_roadmap_and_rechecks(): void
    {
        Queue::fake();
        $this->withFix(['action' => 'remove_task', 'stretch' => 1, 'task' => 'file for trademark.', 'new_text' => '']);

        $this->actingAs($this->goal->user)->post(route('goals.coach.fix', $this->goal));

        $this->assertSame(0, $this->week->tasks()->count());
        $goal = $this->goal->fresh();
        $this->assertNull($goal->plan_fix);
        $this->assertSame(RoadmapStatus::Pending, $goal->plan_check_status);
        $this->assertSame(['Applied the coach\'s fix: removed "File for trademark" from GoalWeek 1'], $goal->pending_changes);
        Queue::assertPushed(ReviewRoadmapChanges::class);
    }

    public function test_applying_add_and_reword_fixes(): void
    {
        Queue::fake();

        $this->withFix(['action' => 'add_task', 'stretch' => 1, 'task' => '', 'new_text' => 'Search the USPTO database']);
        $this->actingAs($this->goal->user)->post(route('goals.coach.fix', $this->goal));

        $this->withFix(['action' => 'reword_task', 'stretch' => 1, 'task' => 'File for trademark', 'new_text' => 'Draft the trademark filing']);
        $this->actingAs($this->goal->user)->post(route('goals.coach.fix', $this->goal));

        $this->assertSame(
            ['Draft the trademark filing', 'Search the USPTO database'],
            $this->week->tasks()->pluck('description')->all(),
        );
    }

    public function test_a_fix_that_no_longer_matches_changes_nothing(): void
    {
        Queue::fake();
        $this->withFix(['action' => 'remove_task', 'stretch' => 1, 'task' => 'A task that is gone', 'new_text' => '']);

        $this->actingAs($this->goal->user)
            ->post(route('goals.coach.fix', $this->goal))
            ->assertSessionHas('status', 'That fix no longer matches your roadmap. Make the change yourself, or keep your plan.');

        $this->assertSame(1, $this->week->tasks()->count());
        Queue::assertNothingPushed();
    }

    public function test_keeping_the_plan_collapses_the_warning(): void
    {
        $this->actingAs($this->goal->user)->post(route('goals.coach.dismiss', $this->goal));

        $this->assertNotNull($this->goal->fresh()->plan_dismissed_at);

        $this->actingAs($this->goal->user)
            ->get(route('goals.show', $this->goal))
            ->assertSee('You kept your plan after an')
            ->assertSee("Show coach's note", false)
            ->assertDontSee('Keep my plan');
    }

    public function test_a_new_review_saves_its_fix_and_reopens_a_dismissed_warning(): void
    {
        $this->goal->forceFill(['plan_dismissed_at' => now(), 'pending_changes' => ['Added a task to GoalWeek 1: "Hire a designer"']])->save();
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('review')->andReturn([
            'verdict' => PlanVerdict::OffTrack,
            'note' => 'GoalWeek 1 is now overloaded.',
            'suggestion' => 'Drop the designer for now.',
            'fix' => ['action' => 'remove_task', 'stretch' => 1, 'task' => 'Hire a designer', 'new_text' => ''],
        ]));

        ReviewRoadmapChanges::dispatchSync($this->goal);

        $goal = $this->goal->fresh();
        $this->assertNull($goal->plan_dismissed_at);
        $this->assertSame('remove_task', $goal->plan_fix['action']);
    }

    public function test_only_the_owner_can_apply_or_dismiss(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('goals.coach.fix', $this->goal))->assertForbidden();
        $this->actingAs($stranger)->post(route('goals.coach.dismiss', $this->goal))->assertForbidden();
    }

    /**
     * @param  array{action: string, stretch: int, task: string, new_text: string}  $fix
     */
    private function withFix(array $fix): void
    {
        $this->goal->forceFill(['plan_fix' => $fix])->save();
    }
}
