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
            ->assertSeeInOrder(['Clubhouse', 'GoalLeagues', 'Highlights', 'My Locker'])
            ->assertSee('aria-label="Set a new goal"', false)
            ->assertSee(route('highlights.index'));
    }

    public function test_the_current_tab_is_marked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('leagues.index'));

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('leagues.index'), '/').'"\s+aria-current="page"/', $response->getContent());
    }

    public function test_the_account_menu_holds_your_locker_settings_and_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('Account menu')
            ->assertSeeInOrder(['My Locker', 'Profile settings', 'Notifications &amp; calendar', 'Log out'], false);
    }

    public function test_goalleagues_and_challenges_are_easy_to_start_or_join(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('leagues.index'))
            ->assertSee('GoalLeagues &amp; Challenges', false)
            ->assertSeeInOrder(['Start a GoalLeague', 'Start a challenge', 'Join with a code']);
    }

    public function test_highlights_tab_has_a_feed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('highlights.index'))
            ->assertOk()
            ->assertSee('Around the League');
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
