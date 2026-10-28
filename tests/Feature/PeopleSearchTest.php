<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PeopleSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->me = User::factory()->create(['name' => 'Avery Lee']);
        User::factory()->create(['name' => 'Priya Shah', 'username' => 'priya.shah', 'email' => 'priya@example.com']);
        User::factory()->create(['name' => 'Priya Hidden', 'username' => 'quiet.one', 'email' => 'hidden@example.com', 'discoverable' => false]);
    }

    public function test_search_finds_people_by_name_or_username_without_showing_emails(): void
    {
        $this->actingAs($this->me)->get(route('people.search', ['q' => 'priya']))
            ->assertOk()
            ->assertSee('Priya Shah')->assertSee('@priya.shah')
            ->assertDontSee('priya@example.com')
            ->assertDontSee('Priya Hidden')
            ->assertSee('Add friend')->assertSee('View');

        $this->actingAs($this->me)->get(route('people.search', ['q' => 'shah']))->assertSee('Priya Shah');
    }

    public function test_people_who_turned_off_discovery_only_turn_up_for_exact_username_or_email(): void
    {
        $this->actingAs($this->me)->get(route('people.search', ['q' => 'quiet']))->assertDontSee('Priya Hidden');
        $this->actingAs($this->me)->get(route('people.search', ['q' => '@quiet.one']))->assertSee('Priya Hidden');
        $this->actingAs($this->me)->get(route('people.search', ['q' => 'HIDDEN@example.com']))->assertSee('Priya Hidden')->assertDontSee('hidden@example.com');

        // Partial emails find no one.
        $this->actingAs($this->me)->get(route('people.search', ['q' => 'example.com']))->assertDontSee('Priya Hidden')->assertDontSee('Priya Shah');
    }

    public function test_the_setting_can_be_turned_off_from_the_profile(): void
    {
        $this->actingAs($this->me)->get(route('profile.edit'))->assertSee('Let people find me by name');
        $this->actingAs($this->me)->put(route('profile.update'), ['name' => 'Avery Lee', 'username' => $this->me->username, 'email' => $this->me->email, 'discoverable' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($this->me->fresh()->discoverable);
    }

    public function test_friend_requests_and_league_invites_work_from_the_results(): void
    {
        $priya = User::where('username', 'priya.shah')->sole();
        $league = League::factory()->create(['commissioner_id' => $this->me->id]);
        $league->members()->create(['user_id' => $this->me->id, 'goal_id' => Goal::factory()->for($this->me)->create()->id]);

        $this->actingAs($this->me)->get(route('people.search', ['q' => 'priya', 'league' => $league->id]))
            ->assertSee('Invite to league')->assertSee(route('leagues.invites.store', $league));

        $this->actingAs($this->me)->post(route('friends.request', $priya))->assertSessionHas('status', 'Friend request sent to Priya.');
        $this->actingAs($this->me)->get(route('people.search', ['q' => 'priya']))->assertSee('Request sent');
        $this->actingAs($priya)->get(route('people.search', ['q' => 'avery']))->assertSee('Accept friend request');
    }

    public function test_search_is_on_friends_and_highlights_with_people_in_your_leagues(): void
    {
        $sam = User::factory()->create(['name' => 'Sam Okafor']);
        $league = League::factory()->create(['commissioner_id' => $this->me->id]);
        $league->members()->create(['user_id' => $this->me->id]);
        $league->members()->create(['user_id' => $sam->id]);

        $this->actingAs($this->me)->get(route('friends.index'))->assertSee('Find people')->assertSee('People in your leagues')->assertSee('Sam Okafor');
        $this->actingAs($this->me)->get(route('highlights.index'))->assertSee('Find people')->assertSee(route('people.search'));
    }

    public function test_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->me)->get(route('people.search', ['q' => 'pr']))->assertOk();
        }

        $this->actingAs($this->me)->get(route('people.search', ['q' => 'pr']))->assertTooManyRequests();
    }
}
