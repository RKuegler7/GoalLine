<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeagueGoalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.timezone')));
        $owner = User::factory()->create();
        $this->league = League::factory()->create([
            'commissioner_id' => $owner->id, 'name' => 'Dojo Go Running', 'category' => GoalCategory::Fitness,
            'starts_on' => '2026-11-16', 'ends_on' => '2027-03-21', 'goal_title' => 'Run a marathon', 'goal_target_date' => '2027-03-20',
        ]);
        $this->league->members()->create(['user_id' => $owner->id]);
    }

    public function test_a_member_with_a_league_goal_sees_it_and_this_weeks_tasks_under_now(): void
    {
        $member = User::factory()->create();
        $goal = Goal::factory()->for($member)->create(['title' => 'Run a marathon', 'target_date' => '2027-03-20']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-15', 'focus' => 'Base', 'milestone' => 'Done'])
            ->tasks()->create(['position' => 1, 'description' => 'Easy run, 30 minutes', 'planned_on' => '2026-11-11']);
        $this->league->members()->create(['user_id' => $member->id, 'goal_id' => $goal->id]);

        $this->actingAs($member)->get(route('dashboard'))->assertOk()
            ->assertSee('Run a marathon')
            ->assertSee('Easy run, 30 minutes')
            ->assertDontSee('data-league-setup', false);
    }

    public function test_a_member_without_a_goal_gets_a_set_your_goal_card_and_the_league_still_shows(): void
    {
        $member = User::factory()->create();
        $this->league->members()->create(['user_id' => $member->id]);

        $this->actingAs($member)->get(route('dashboard'))->assertOk()
            ->assertSee('data-league-setup="needs_goal"', false)
            ->assertSee('Set your goal for Dojo Go Running')
            ->assertSee(route('leagues.my-goal', $this->league), false);
        $this->actingAs($member)->get(route('leagues.index'))->assertSee('Dojo Go Running');

        // The card's page is the goal step, and setting the goal attaches it and starts the questions.
        $this->actingAs($member)->get(route('leagues.my-goal', $this->league))->assertOk()->assertSee('Use this goal')->assertSee('Run a marathon by March 20');
        $this->actingAs($member)->post(route('leagues.my-goal.store', $this->league), ['title' => 'Run a marathon', 'category' => 'fitness', 'target_date' => '2027-03-20'])
            ->assertRedirect();
        $goal = $member->goals()->sole();
        $this->assertSame($goal->id, $this->league->memberFor($member)->goal_id);

        // Questions still open: the card resumes them.
        $this->actingAs($member)->get(route('dashboard'))->assertSee('Finish setting up your goal for Dojo Go Running')
            ->assertSee(route('goals.intake', ['goal' => $goal, 'from' => 'joined']), false);
        $this->actingAs($member)->get(route('leagues.my-goal', $this->league))->assertRedirect(route('goals.intake', ['goal' => $goal, 'from' => 'joined']));

        // Answered: the plan is on its way, and the goal is one tap away.
        $this->actingAs($member)->post(route('goals.intake.store', $goal), ['from' => 'joined', 'skip' => 1]);
        $this->actingAs($member)->get(route('dashboard'))->assertSee('Your plan for Dojo Go Running is on its way')->assertSee(route('goals.show', $goal), false);
    }

    public function test_a_member_with_a_spare_goal_can_use_it_in_the_league(): void
    {
        $member = User::factory()->create();
        $this->league->members()->create(['user_id' => $member->id]);
        $spare = Goal::factory()->for($member)->create(['title' => 'Swim a mile', 'target_date' => '2027-02-01']);

        $this->actingAs($member)->get(route('dashboard'))->assertSee('Use my goal in this league: Swim a mile');
        $this->actingAs($member)->put(route('leagues.membership.update', $this->league), ['goal_id' => $spare->id])->assertRedirect();
        $this->assertSame($spare->id, $this->league->memberFor($member)->goal_id);
    }

    public function test_every_way_of_joining_leaves_a_goal_to_see(): void
    {
        // Link (goal step), code (goal step), and an in-app invite by username (goal step).
        $link = User::factory()->create();
        $this->actingAs($link)->post(route('leagues.invite.goal', $this->league->shareableInvite()->token), ['title' => 'Run a marathon', 'category' => 'fitness', 'target_date' => '2027-03-20']);
        $code = User::factory()->create();
        $this->actingAs($code)->post(route('leagues.join.goal', $this->league->invite_code), ['title' => 'Run a marathon', 'category' => 'fitness', 'target_date' => '2027-03-20']);
        $named = User::factory()->create();
        $invite = $this->league->invites()->create(['kind' => LeagueInvite::USERNAME, 'invitee_id' => $named->id, 'expires_at' => now()->addDays(14)]);
        $this->actingAs($named)->post(route('leagues.invite.goal', $invite->token), ['title' => 'Run a marathon', 'category' => 'fitness', 'target_date' => '2027-03-20']);

        foreach ([$link, $code, $named] as $member) {
            $goal = $this->league->memberFor($member)?->goal;
            $this->assertNotNull($goal, 'Joined with a goal attached.');
            $this->assertNotNull(GoalIntake::where('goal_id', $goal->id)->first());
            $this->actingAs($member)->get(route('dashboard'))->assertSee('Run a marathon')->assertSee('Finish setting up your goal for Dojo Go Running');
        }
    }

    public function test_the_demo_member_has_goals_in_every_league(): void
    {
        $this->get(route('demo'))->assertRedirect();
        $visitor = auth()->user();

        foreach ($visitor->leagues as $league) {
            $this->assertNotNull($league->pivot->goal_id, $league->name);
        }
        $this->get(route('dashboard'))->assertDontSee('data-league-setup="needs_goal"', false);
    }
}
