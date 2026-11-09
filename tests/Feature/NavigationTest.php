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
            ->assertSeeInOrder(['Clubhouse', 'GoalLeagues', 'Matchups', 'Highlights', 'My Locker'])
            ->assertSee('aria-label="Set a new goal"', false)
            ->assertSee(route('highlights.index'))
            ->assertSee(route('matchups'));
    }

    public function test_the_matchups_tab_opens_this_weeks_matchups_or_explains_how_to_get_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('matchups'))->assertRedirect(route('leagues.index'));

        $league = League::factory()->create(['commissioner_id' => $user->id, 'starts_on' => now()->subWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(6)->endOfWeek()]);
        $league->members()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('matchups'))->assertRedirect(route('leagues.matchups', $league));
        // The tab bar stays on the matchup page, with Matchups marked as the current tab.
        $page = $this->actingAs($user)->get(route('leagues.matchups', $league))->assertOk()->assertSee('aria-label="Main"', false)->getContent();
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('matchups'), '/').'"\s+aria-current="page"/', $page);
    }

    public function test_check_in_is_one_tap_away_on_main_screens(): void
    {
        $user = User::factory()->create();

        foreach ([route('dashboard'), route('leagues.index'), route('highlights.index'), route('profile.show')] as $page) {
            $this->actingAs($user)->get($page)->assertSee('data-checkin-open', false)->assertSee('data-live-source="'.route('checkin.sheet').'"', false);
        }

        $this->actingAs($user)->get(route('checkin.sheet'))->assertOk()
            ->assertSee('data-region="checkin-sheet"', false)
            ->assertSee('Today&rsquo;s Gameplan', false)
            ->assertDontSee('<html', false);
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
