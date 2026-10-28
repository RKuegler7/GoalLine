<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoalNextStepTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->me = User::factory()->create();
        $this->actingAs($this->me)->post(route('goals.store'), ['title' => 'Run a half marathon', 'category' => 'fitness', 'target_date' => today()->addMonths(3)->toDateString()])
            ->assertRedirect(route('goals.next', $this->me->goals()->sole()));
        $this->goal = $this->me->goals()->sole();
    }

    public function test_setting_a_goal_asks_play_it_with_friends(): void
    {
        $this->actingAs($this->me)->get(route('goals.next', $this->goal))
            ->assertOk()->assertSee('Play it with friends?')->assertSee('Create a league')->assertSee('Join a league')->assertSee('Keep it solo');
    }

    public function test_create_a_league_fills_it_in_from_the_goal_and_leads_to_invites(): void
    {
        $this->actingAs($this->me)->post(route('goals.next.choose', $this->goal), ['choice' => 'create'])
            ->assertRedirect(route('leagues.create', ['goal' => $this->goal->id]));

        $this->actingAs($this->me)->get(route('leagues.create', ['goal' => $this->goal->id]))
            ->assertSee('Filled in from')->assertSee('value="Run a half marathon League"', false)
            ->assertSee('<option value="fitness" selected', false);

        $monday = today()->next('Monday')->toDateString();
        $response = $this->actingAs($this->me)->post(route('leagues.store'), [
            'name' => 'Run a half marathon League', 'category' => 'fitness', 'starts_on' => $monday,
            'ends_on' => today()->addMonths(3)->toDateString(), 'goal_id' => $this->goal->id,
        ]);
        $league = League::sole();
        $response->assertRedirect(route('leagues.show', $league).'#invite');
        $this->assertSame(GoalCategory::Fitness, $league->category);
        $this->assertSame($this->goal->id, $league->memberFor($this->me)->goal_id);
        $this->actingAs($this->me)->get(route('leagues.show', $league))->assertSee('Invite your people')->assertSee('Invite friends');
    }

    public function test_join_a_league_finds_it_by_code_with_this_goal_picked(): void
    {
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $league->commissioner_id]);

        $this->actingAs($this->me)->post(route('goals.next.choose', $this->goal), ['choice' => 'join', 'code' => 'nope'])->assertSessionHasErrors('code');
        $this->actingAs($this->me)->post(route('goals.next.choose', $this->goal), ['choice' => 'join', 'code' => strtolower(str_replace('-', '', $league->invite_code))])
            ->assertRedirect(route('leagues.join', ['code' => $league->invite_code, 'goal' => $this->goal->id]));
    }

    public function test_keep_it_solo_goes_to_the_goal(): void
    {
        $this->actingAs($this->me)->post(route('goals.next.choose', $this->goal), ['choice' => 'solo'])
            ->assertRedirect(route('goals.show', $this->goal))
            ->assertSessionHas('status', "Going solo: it's you against the line. You can start or join a GoalLeague any time.");
    }

    public function test_only_the_goal_owner_sees_the_next_step(): void
    {
        $this->actingAs(User::factory()->create())->get(route('goals.next', $this->goal))->assertForbidden();
    }
}
