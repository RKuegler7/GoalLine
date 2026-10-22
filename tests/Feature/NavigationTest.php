<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_pages_have_the_main_tabs_and_a_new_goal_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Clubhouse', 'GoalLeagues', 'Wins', 'My Locker'])
            ->assertSee('aria-label="Set a new goal"', false)
            ->assertSee(route('wins.index'));
    }

    public function test_the_current_tab_is_marked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('leagues.index'));

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('leagues.index'), '/').'"\s+aria-current="page"/', $response->getContent());
    }

    public function test_wins_tab_has_a_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('wins.index'))
            ->assertOk()
            ->assertSee('Your Wins feed is on its way');
    }

    public function test_leagues_tab_shows_unread_chat_messages(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id]);
        $league->members()->create(['user_id' => $user->id, 'last_read_message_id' => 0]);
        $league->members()->create(['user_id' => $friend->id]);
        $league->messages()->create(['user_id' => $friend->id, 'body' => 'Morning!']);
        $league->messages()->create(['user_id' => $friend->id, 'body' => 'You up?']);

        $this->assertSame(2, $user->unreadLeagueMessages());
        $this->actingAs($user)->get(route('dashboard'))->assertSee('2 unread');
    }
}
