<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\PlanRefinement;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\AiCodeSplit;
use App\Services\IntakeQuestions;
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

        $first = $this->goal->intake->questions[0]['text'];
        $this->assertContains("Wrote {$questions} intake questions just for this goal, like \u{201C}{$first}\u{201D}.", $split['ai']);
        $this->assertContains('Drafted your 3 GoalWeeks: a focus, a milestone and the tasks for each.', $split['ai']);
        $this->assertContains("Explained the plan in its own words: \u{201C}Build, then sharpen.\u{201D}", $split['ai']);
        $this->assertContains('Reworked your plan once when you asked. You approved each change.', $split['ai']);
        $this->assertContains('We raised GoalWeek 3 to your minimum pace of 3 points (+2 points).', $split['code']);
        $this->assertContains('Locked GoalWeek 2 at the start of Monday, Oct 26, and the 1 before it, so the plan you\'re scored on can\'t move.', $split['code']);
        $this->assertContains('Set the dates of all 3 GoalWeeks from your start and deadline, and kept the coach\'s draft only because its weeks matched them.', $split['code']);
        $this->assertContains('Capped AI use at 20 coaching requests per member a day, and $2 a day for all of GoalLine.', $split['code']);
        $this->assertFalse($split['sample']);
    }

    public function test_before_kickoff_it_says_when_the_first_week_will_lock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00', config('app.timezone')));

        $this->assertContains('Will lock GoalWeek 1 at the start of Monday, Oct 19, so the plan you\'re scored on can\'t move.', AiCodeSplit::forGoal($this->goal->fresh())['code']);
    }

    public function test_the_panel_never_shows_intake_answers_and_other_members_never_see_it(): void
    {
        $this->goal->intake->answer([['id' => 'experience', 'question' => 'Experience?', 'answer' => 'Private note: failed it twice before', 'status' => IntakeQuestions::ANSWERED]]);

        // The owner sees their own answers elsewhere on the page, but the panel itself never quotes them.
        $html = $this->actingAs($this->user)->get(route('goals.show', $this->goal))->assertOk()->assertSee('How this plan was built')->getContent();
        preg_match('#<section[^>]*data-ai-code-split>.*?</section>#s', $html, $panel);
        $this->assertNotEmpty($panel);
        $this->assertStringNotContainsString('failed it twice before', $panel[0]);

        // Someone else can't open the goal page, and a league mate's view of the plan has no panel and no answers.
        $mate = User::factory()->create();
        $this->actingAs($mate)->get(route('goals.show', $this->goal))->assertForbidden();

        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-08']);
        $member = $league->members()->create(['user_id' => $this->user->id, 'goal_id' => $this->goal->id]);
        $league->members()->create(['user_id' => $mate->id]);
        $this->actingAs($mate)->get(route('leagues.members.plan', [$league, $member]))->assertOk()
            ->assertDontSee('How this plan was built')
            ->assertDontSee('data-ai-code-split', false)
            ->assertDontSee('failed it twice before');
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
            ->assertSee('How this plan was built')
            ->assertSee('Drafted your 3 GoalWeeks')
            ->assertSee('AI never scores your week or decides disputes.')
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
            ->assertSee('Caps how much AI GoalLine can use each day')
            ->assertSee('AI never scores your week or decides disputes.');
    }
}
