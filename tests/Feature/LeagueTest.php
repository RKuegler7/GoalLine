<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeagueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21'); // A Wednesday; the next kickoff Monday is Oct 26.
    }

    public function test_member_can_start_a_league_as_commissioner(): void
    {
        $goal = Goal::factory()->create(['target_date' => '2026-12-15']);

        $this->actingAs($goal->user)->get(route('leagues.create'))->assertOk()->assertSee('Monday, Oct 26');

        $response = $this->actingAs($goal->user)->post(route('leagues.store'), [
            'name' => 'FAR Exam Crew',
            'starts_on' => '2026-10-26',
            'ends_on' => '2026-12-15',
            'goal_id' => $goal->id,
        ]);

        $league = League::sole();
        $response->assertRedirect(route('leagues.show', $league).'#invite');
        $this->assertTrue($league->commissioner->is($goal->user));
        $this->assertSame('2026-12-20', $league->ends_on->toDateString(), 'Seasons end on a Sunday.');
        $this->assertSame(8, $league->weeksCount());
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $league->invite_code);
        $this->assertSame($goal->id, $league->memberFor($goal->user)->goal_id);
    }

    public function test_a_league_can_be_started_with_a_new_goal_typed_right_in(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Goal::factory()->for($user)->create(['title' => 'Run a half marathon']);

        $this->actingAs($user)->get(route('leagues.create'))->assertOk()
            ->assertSee('+ A new goal for this league')->assertSee('name="goal_title"', false);

        $this->actingAs($user)->post(route('leagues.store'), [
            'name' => 'FAR Exam Crew', 'category' => 'academic', 'starts_on' => '2026-10-26', 'ends_on' => '2026-12-15', 'goal_id' => 'new',
        ])->assertSessionHasErrors(['goal_title', 'goal_target_date']);

        $response = $this->actingAs($user)->post(route('leagues.store'), [
            'name' => 'FAR Exam Crew', 'category' => 'academic', 'starts_on' => '2026-10-26', 'ends_on' => '2026-12-15',
            'goal_id' => 'new', 'goal_title' => 'Pass FAR on December 15', 'goal_target_date' => '2026-12-15',
        ]);

        $goal = $user->goals()->where('title', 'Pass FAR on December 15')->sole();
        $league = League::sole();
        $this->assertSame(GoalCategory::Academic, $goal->category);
        $this->assertSame($goal->id, $league->memberFor($user)->goal_id);
        $response->assertRedirect(route('goals.intake', ['goal' => $goal, 'from' => 'league_home']));

        // After the questions, straight to inviting people.
        $this->actingAs($user)->post(route('goals.intake.store', $goal), ['from' => 'league_home', 'skip' => 1])
            ->assertRedirect(route('leagues.show', $league).'#invite');
    }

    public function test_someone_with_no_goals_can_still_start_a_league(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('leagues.create'))->assertOk()->assertSee('Create GoalLeague')->assertSee('<option value="new" selected', false);
    }

    public function test_your_league_goal_can_be_edited_switched_or_replaced_with_a_new_one(): void
    {
        Queue::fake();
        $goal = Goal::factory()->create(['title' => 'Pass FAR']);
        $other = Goal::factory()->for($goal->user)->create(['title' => 'Pass AUD']);
        $league = League::factory()->create(['commissioner_id' => $goal->user_id]);
        $league->members()->create(['user_id' => $goal->user_id, 'goal_id' => $goal->id]);

        $this->actingAs($goal->user)->get(route('leagues.show', $league))->assertOk()
            ->assertSee('Your goal in this league')
            ->assertSee(route('goals.edit', $goal))
            ->assertSee('Pass AUD')
            ->assertSee(route('goals.create', ['league' => $league->id]));

        $this->actingAs($goal->user)->put(route('leagues.membership.update', $league), ['goal_id' => $other->id]);
        $this->assertSame($other->id, $league->memberFor($goal->user)->goal_id);

        $this->actingAs($goal->user)->get(route('goals.create', ['league' => $league->id]))->assertOk()
            ->assertSee('Set your goal for this league')->assertSee('name="league_id" value="'.$league->id.'"', false);
        $this->actingAs($goal->user)->post(route('goals.store'), [
            'title' => 'Pass REG', 'category' => 'academic', 'target_date' => '2026-12-20', 'league_id' => $league->id,
        ]);
        $new = $goal->user->goals()->where('title', 'Pass REG')->sole();
        $this->assertSame($new->id, $league->fresh()->memberFor($goal->user)->goal_id);

        // A league you're not in can't be targeted.
        $elsewhere = League::factory()->create();
        $this->actingAs($goal->user)->post(route('goals.store'), ['title' => 'Sneaky', 'category' => 'academic', 'target_date' => '2026-12-20', 'league_id' => $elsewhere->id]);
        $this->assertNull($elsewhere->memberFor($goal->user));
    }

    public function test_joiners_with_goals_can_set_a_new_one_for_the_league(): void
    {
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $league->commissioner_id]);
        $joiner = Goal::factory()->create();

        $this->actingAs($joiner->user)->get(route('leagues.join', $league->invite_code))->assertOk()
            ->assertSee("Set the goal you'll play", false)
            ->assertSee('Play a goal you already have')
            ->assertSee('data-join-goal="custom"', false);
    }

    public function test_kickoff_must_be_an_upcoming_monday_and_the_goal_must_be_yours(): void
    {
        $user = User::factory()->create();
        $someoneElsesGoal = Goal::factory()->create();

        $this->actingAs($user)->post(route('leagues.store'), [
            'name' => 'Bad League',
            'starts_on' => '2026-10-27',
            'ends_on' => '2026-12-15',
            'goal_id' => $someoneElsesGoal->id,
        ])->assertSessionHasErrors(['starts_on', 'goal_id']);

        $this->assertSame(0, League::count());
    }

    public function test_people_join_with_a_code_before_kickoff(): void
    {
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $league->commissioner_id]);
        $joiner = Goal::factory()->create();

        $this->actingAs($joiner->user)
            ->post(route('leagues.find'), ['code' => strtolower(str_replace('-', '', $league->invite_code))])
            ->assertRedirect(route('leagues.join', $league->invite_code));

        $this->actingAs($joiner->user)->get(route('leagues.join', $league->invite_code))->assertOk()->assertSee('Play a goal you already have');

        $this->actingAs($joiner->user)
            ->post(route('leagues.join.store', $league->invite_code), ['goal_id' => $joiner->id])
            ->assertRedirect(route('leagues.show', $league));

        $this->assertSame(2, $league->members()->count());
        $this->actingAs($joiner->user)->get(route('leagues.show', $league))->assertOk()->assertSee($league->invite_code);
    }

    public function test_unknown_codes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('leagues.find'), ['code' => 'NOPE-NOPE'])
            ->assertSessionHasErrors('code');
    }

    public function test_late_joiners_play_from_the_current_week_and_a_finished_season_says_so(): void
    {
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $league->commissioner_id]);
        $joiner = Goal::factory()->create(['target_date' => $league->ends_on->copy()->addMonth()]);
        $this->travelTo($league->starts_on->copy()->addDays(8)); // Week 2.

        $this->actingAs($joiner->user)->get(route('leagues.join', $league->invite_code))->assertOk()
            ->assertSee("You'll play from GoalWeek 2", false);
        $this->actingAs($joiner->user)
            ->post(route('leagues.join.store', $league->invite_code), ['goal_id' => $joiner->id])
            ->assertRedirect(route('leagues.show', $league));
        $this->assertSame(2, $league->memberFor($joiner->user)->joined_week);

        $this->travelTo($league->ends_on->copy()->addDays(2));
        $late = Goal::factory()->create();
        $this->actingAs($late->user)->get(route('leagues.join', $league->invite_code))->assertOk()->assertSee('This season is over.')->assertSee('Start your own GoalLeague');
        $this->actingAs($late->user)->post(route('leagues.join.store', $league->invite_code), ['goal_id' => $late->id])
            ->assertRedirect(route('leagues.join', $league->invite_code));
        $this->assertNull($league->memberFor($late->user));
    }

    public function test_only_members_can_see_a_league(): void
    {
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $league->commissioner_id]);

        $this->actingAs(User::factory()->create())->get(route('leagues.show', $league))->assertForbidden();
    }

    public function test_members_can_leave_and_commissioners_can_call_off_before_kickoff(): void
    {
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $league->commissioner_id]);
        $member = User::factory()->create();
        $league->members()->create(['user_id' => $member->id]);

        $this->actingAs($league->commissioner)->delete(route('leagues.membership.destroy', $league))->assertForbidden();

        $this->actingAs($member)->delete(route('leagues.membership.destroy', $league))->assertRedirect(route('leagues.index'));
        $this->assertNull($league->fresh()->memberFor($member));

        $this->actingAs($league->commissioner)->delete(route('leagues.destroy', $league))->assertRedirect(route('leagues.index'));
        $this->assertModelMissing($league);
    }

    public function test_members_can_switch_their_league_goal(): void
    {
        $league = League::factory()->create();
        $old = Goal::factory()->for($league->commissioner)->create();
        $new = Goal::factory()->for($league->commissioner)->create();
        $league->members()->create(['user_id' => $league->commissioner_id, 'goal_id' => $old->id]);

        $this->actingAs($league->commissioner)->put(route('leagues.membership.update', $league), ['goal_id' => $new->id]);

        $this->assertSame($new->id, $league->fresh()->memberFor($league->commissioner)->goal_id);
    }

    public function test_a_league_can_hold_64_members(): void
    {
        $league = League::factory()->create();
        foreach (User::factory()->count(League::MAX_MEMBERS)->create() as $user) {
            $league->members()->create(['user_id' => $user->id]);
        }

        $this->assertFalse($league->fresh()->isJoinable());
    }
}
