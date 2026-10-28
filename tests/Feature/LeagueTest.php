<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($joiner->user)->get(route('leagues.join', $league->invite_code))->assertOk()->assertSee('Join GoalLeague');

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

    public function test_no_one_can_join_after_kickoff(): void
    {
        $league = League::factory()->create();
        $joiner = Goal::factory()->create();
        $this->travelTo($league->starts_on);

        $this->actingAs($joiner->user)
            ->post(route('leagues.join.store', $league->invite_code), ['goal_id' => $joiner->id])
            ->assertForbidden();
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
