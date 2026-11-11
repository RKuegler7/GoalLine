<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\PlanRefinement;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\AiCodeSplit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiCodeSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        config(['goalline.demo.live_ai' => false]);
        $this->travelTo(Carbon::parse('2026-10-28 12:00', config('app.timezone'))); // Wednesday of week 2.
        $this->user = User::factory()->create();
        $this->goal = Goal::factory()->for($this->user)->create(['title' => 'Pass the MCAT', 'target_date' => '2026-12-31']);
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'plan_source' => 'coach', 'roadmap_summary' => 'Build, then sharpen.'])->save();

        foreach ([['2026-10-19', '2026-10-25'], ['2026-10-26', '2026-11-01'], ['2026-11-02', '2026-11-08']] as $index => [$starts, $ends]) {
            $week = $this->goal->weeks()->create(['position' => $index + 1, 'starts_on' => $starts, 'ends_on' => $ends, 'focus' => 'Study', 'milestone' => 'Done.']);
            $week->forceFill(['floor_points' => 3])->save();
            $week->tasks()->create(['position' => 1, 'description' => 'Practice set'])->forceFill(['completed_at' => $index === 0 ? '2026-10-20 10:00' : null])->saveQuietly();
        }

        GoalIntake::factory()->for($this->goal)->create(['source' => GoalIntake::SOURCE_AI]);
    }

    public function test_it_lists_what_the_ai_drafted_and_what_code_enforced_with_live_examples(): void
    {
        $this->goal->weeks()->where('position', 3)->update(['floor_note' => 'We raised this week to the minimum pace your deadline needs (+2 points).']);
        PlanRefinement::factory()->for($this->goal)->create(['status' => PlanRefinement::ACCEPTED]);
        $questions = count($this->goal->intake->questions);

        $split = AiCodeSplit::forGoal($this->goal->fresh());

        $this->assertContains("Wrote {$questions} intake questions just for this goal.", $split['ai']);
        $this->assertContains('Drafted your 3 GoalWeeks: a focus, a milestone and the tasks for each.', $split['ai']);
        $this->assertContains('Reworked your plan once when you asked. You approved each change.', $split['ai']);
        $this->assertContains('We raised GoalWeek 3 to your minimum pace.', $split['code']);
        $this->assertContains('Locked 2 GoalWeeks when they started, so the plan you\'re scored on can\'t move.', $split['code']);
        $this->assertFalse($split['sample']);
    }

    public function test_a_starter_plan_is_credited_to_code_not_the_ai(): void
    {
        $this->goal->forceFill(['plan_source' => 'fallback'])->save();
        $this->goal->intake->forceFill(['source' => GoalIntake::SOURCE_FALLBACK])->save();

        $split = AiCodeSplit::forGoal($this->goal->fresh());

        $this->assertSame([], $split['ai']);
        $this->assertContains('Built a starter plan from your goal\'s category when the coach was unavailable.', $split['code']);
        $this->assertContains('Checked every GoalWeek against the minimum pace your deadline needs.', $split['code']);
    }

    public function test_the_owner_sees_the_panel_on_the_goal_page_and_demo_accounts_are_told_it_is_a_sample(): void
    {
        $this->actingAs($this->user)->get(route('goals.show', $this->goal))
            ->assertOk()
            ->assertSee('What the AI did, what code did')
            ->assertSee('Drafted your 3 GoalWeeks')
            ->assertDontSee('saved samples');

        $this->user->forceFill(['demo_sandbox' => 'test-sandbox'])->save();

        $this->actingAs($this->user)->get(route('goals.show', $this->goal))->assertSee('saved samples, so no live AI is called');
    }

    public function test_the_about_page_shows_the_general_split_without_internals(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Who does what')
            ->assertSee('Scores every check-in, band and matchup')
            ->assertSee('Caps how much AI GoalLine can use each day');
    }
}
