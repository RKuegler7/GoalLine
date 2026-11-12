<?php

namespace Tests\Feature;

use App\EmailVerificationLink;
use App\GoalCategory;
use App\Jobs\GenerateRoadmap;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\User;
use App\PendingInvite;
use App\RoadmapStatus;
use App\Services\LeagueTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JoinGoalFlowTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['goalline.beta.invite_only' => false]);
        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.timezone'))); // Wednesday.
        $this->owner = User::factory()->create(['name' => 'Corey Dojo']);
        $this->league = League::factory()->create([
            'commissioner_id' => $this->owner->id, 'name' => 'Dojo Go Running', 'category' => GoalCategory::Fitness,
            'starts_on' => '2026-11-16', 'ends_on' => '2027-03-21',
            'goal_title' => 'Run a marathon', 'goal_target_date' => '2027-03-20', 'goal_description' => 'Any 26.2 this spring.',
        ]);
        $this->league->members()->create(['user_id' => $this->owner->id]);
    }

    public function test_the_goal_step_offers_the_league_goal_first_with_matching_examples(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get($this->league->shareableInvite()->url())->assertOk()
            ->assertSeeInOrder(['Same goal as the league', 'Run a marathon by March 20', 'Use this goal', 'Make it yours', 'Choose a different goal'])
            ->assertSee('First time?')
            ->assertSee('Return from injury')
            ->assertSee('not medical advice')
            ->assertSee('placeholder="Run a half marathon by April 12"', false)
            ->assertDontSee('Pass FAR');
    }

    public function test_use_this_goal_joins_and_goes_straight_into_the_intake_then_the_plan(): void
    {
        $member = User::factory()->create(['onboarded_at' => null]);
        $invite = $this->league->shareableInvite();

        $response = $this->actingAs($member)->post(route('leagues.invite.goal', $invite->token), [
            'title' => 'Run a marathon', 'category' => 'fitness', 'target_date' => '2027-03-20',
        ]);

        $goal = $member->goals()->sole();
        $response->assertRedirect(route('goals.intake', ['goal' => $goal, 'from' => 'joined']));
        $this->assertSame(['Run a marathon', GoalCategory::Fitness, '2027-03-20'], [$goal->title, $goal->category, $goal->target_date->toDateString()]);
        $this->assertSame($goal->id, $this->league->memberFor($member)->goal_id);
        $this->assertNull($this->league->memberFor($member)->joined_week, 'Before kickoff: the whole season.');
        $this->assertNotNull(GoalIntake::where('goal_id', $goal->id)->first());
        $this->assertNotNull($member->fresh()->onboarded_at);

        // Answering (or skipping) the questions builds the plan, then the goal page shows it to accept.
        $this->actingAs($member)->post(route('goals.intake.store', $goal), ['from' => 'joined', 'skip' => 1])
            ->assertRedirect(route('goals.show', $goal));
        Queue::assertPushed(GenerateRoadmap::class, fn (GenerateRoadmap $job) => $job->goal->is($goal));
        $this->assertSame(RoadmapStatus::Pending, $goal->fresh()->roadmap_status);
    }

    public function test_make_it_yours_keeps_dates_inside_the_season_unless_the_member_chooses_otherwise(): void
    {
        $member = User::factory()->create();
        $token = $this->league->shareableInvite()->token;
        $fields = ['title' => 'Run a marathon', 'category' => 'fitness', 'target_result' => 'Under 4:30', 'description' => 'My first one.', 'chips' => ['First time?', 'Fewer training days']];

        $this->actingAs($member)->post(route('leagues.invite.goal', $token), [...$fields, 'target_date' => '2027-05-01'])
            ->assertSessionHasErrors(['target_date' => 'The league season ends March 21, 2027. Pick a date by then, or tick "Keep my later date": your matchups end with the season, and you keep going against the line after it.']);
        $this->assertSame(0, $member->goals()->count());

        $this->actingAs($member)->post(route('leagues.invite.goal', $token), [...$fields, 'target_date' => '2026-11-10'])->assertSessionHasErrors('target_date');

        $this->actingAs($member)->post(route('leagues.invite.goal', $token), [...$fields, 'target_date' => '2027-05-01', 'past_season' => 1])->assertRedirect();
        $goal = $member->goals()->sole();
        $this->assertSame('Under 4:30', $goal->target_result);
        $this->assertStringContainsString('My first one.', $goal->context);
        $this->assertStringContainsString('first time going for a goal like this', $goal->context);
        $this->assertStringContainsString('only train a few days a week', $goal->context);
    }

    public function test_a_different_goal_works_too(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('leagues.join.goal', $this->league->invite_code), [
            'title' => 'Swim a mile without stopping', 'category' => 'fitness', 'target_date' => '2027-02-01',
        ])->assertRedirect();

        $this->assertSame('Swim a mile without stopping', $this->league->memberFor($member)->goal->title);
    }

    public function test_signed_out_visitors_come_back_to_the_same_step_through_sign_up_and_verification(): void
    {
        $invite = $this->league->shareableInvite();

        $this->get($invite->url())->assertOk()->assertSee('Sign in to join')->assertSee('Goal: Run a marathon by March 20');
        $this->post(route('register'), [
            'name' => 'Nova Lee', 'email' => 'nova@example.com', 'password' => 'a-strong-password-123', 'password_confirmation' => 'a-strong-password-123',
        ])->assertRedirect($invite->url());
        $nova = User::where('email', 'nova@example.com')->sole();
        $this->assertSame($invite->url(), PendingInvite::url($nova));

        // Not verified yet: the step says so, and joining waits.
        $this->actingAs($nova)->get($invite->url())->assertSee('One step first: verify your email.')->assertDontSee('Use this goal');

        // Verifying (signed in) comes straight back to the goal step.
        $this->actingAs($nova)->get(EmailVerificationLink::for($nova))->assertRedirect($invite->url());
        $this->actingAs($nova->fresh())->get($invite->url())->assertSee('Use this goal');
    }

    public function test_verifying_on_another_device_logs_in_and_returns_to_the_invite(): void
    {
        $invite = $this->league->shareableInvite();
        $nova = User::factory()->unverified()->create(['email' => 'nova@example.com', 'password' => 'a-strong-password-123']);
        PendingInvite::remember($nova, $invite->url());

        $this->get(EmailVerificationLink::for($nova))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => 'nova@example.com', 'password' => 'a-strong-password-123'])->assertRedirect($invite->url());
    }

    public function test_a_late_joiner_starts_in_the_current_week_and_plays_the_median_first(): void
    {
        [$ann, $bea] = [$this->member('Ann'), $this->member('Bea')];
        $this->travelTo(Carbon::parse('2026-11-25 10:00', config('app.timezone'))); // Week 2.
        $cy = User::factory()->create();

        $this->actingAs($cy)->post(route('leagues.join.goal', $this->league->invite_code), ['title' => 'Run a marathon', 'category' => 'fitness', 'target_date' => '2027-03-20'])->assertRedirect();
        $member = $this->league->memberFor($cy);
        $this->assertSame(2, $member->joined_week);

        $table = new LeagueTable($this->league->fresh());
        $names = fn (int $week) => collect($table->pairings($week))->map(fn (array $pair) => [$pair[0]->user->name, $pair[1]?->user->name])->sortBy(fn ($pair) => $pair[0])->values()->all();

        // Week 1: Cy isn't in it. Week 2: everyone else as before, Cy against the median. Week 3 on: a rotation of four.
        $this->assertNotContains($cy->id, $table->membersIn(1)->pluck('user_id'));
        $this->assertCount(2, $table->pairings(1));
        $this->assertContains([$cy->name, null], $names(2));
        $this->assertSame(collect($table->pairings(1))->reject(fn ($pair) => $pair[0]->is($member))->count(), collect($table->pairings(2))->reject(fn ($pair) => $pair[0]->is($member))->count());
        $this->assertCount(2, $table->pairings(3), 'Four members: two matchups, no bye.');
        $this->assertTrue(collect($table->pairings(3))->every(fn ($pair) => $pair[1] !== null));
    }

    public function test_odd_and_small_leagues_still_pair_with_byes(): void
    {
        $this->member('Ann');
        $table = new LeagueTable($this->league->fresh());
        $this->assertCount(1, $table->pairings(1), 'Two members: one matchup.');
        $this->assertNotNull($table->pairings(1)[0][1]);

        $this->member('Bea');
        $table = new LeagueTable($this->league->fresh());
        $this->assertCount(2, $table->pairings(1), 'Three members: one matchup and one bye.');
        $this->assertSame(1, collect($table->pairings(1))->filter(fn ($pair) => $pair[1] === null)->count());
    }

    public function test_a_full_league_has_a_friendly_page(): void
    {
        foreach (range(2, League::MAX_MEMBERS) as $index) {
            $this->league->members()->create(['user_id' => User::factory()->create()->id]);
        }
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('leagues.join', $this->league->invite_code))->assertOk()
            ->assertSee('This GoalLeague is full.')
            ->assertSee('Start your own GoalLeague')
            ->assertDontSee('Use this goal');
        $this->actingAs($member)->post(route('leagues.join.goal', $this->league->invite_code), ['title' => 'x', 'category' => 'fitness', 'target_date' => '2027-01-01'])
            ->assertRedirect(route('leagues.join', $this->league->invite_code));
        $this->assertSame(0, $member->goals()->count());
    }

    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $goal = Goal::factory()->for($user)->create(['target_date' => '2027-03-20']);
        $this->league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);

        return $user;
    }
}
