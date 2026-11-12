<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\RoadmapStatus;
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

        // No league: the tab opens its own page (never a bounce to another tab), with Matchups still current.
        $page = $this->actingAs($user)->get(route('matchups'))->assertOk()
            ->assertSee('No matchup yet.')
            ->assertSee('A matchup is your week against a friend, scored against your own plans.')
            ->assertSee('Start a GoalLeague')
            ->assertSee('Join with a code')
            ->assertDontSee('Go solo against the line')
            ->getContent();
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('matchups'), '/').'"[^>]*aria-current="page"/', $page);

        $league = League::factory()->create(['commissioner_id' => $user->id, 'starts_on' => now()->subWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(6)->endOfWeek()]);
        $league->members()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('matchups'))->assertRedirect(route('leagues.matchups', $league));
        // The tab bar stays on the matchup page, with Matchups marked as the current tab.
        $page = $this->actingAs($user)->get(route('leagues.matchups', $league))->assertOk()->assertSee('aria-label="Main"', false)->getContent();
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('matchups'), '/').'"[^>]*aria-current="page"/', $page);
    }

    public function test_the_matchups_tab_explains_kickoff_season_over_and_solo(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['title' => 'Learn to swim', 'target_date' => now()->addMonths(2)]);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => now()->startOfWeek(), 'ends_on' => now()->endOfWeek(), 'focus' => 'Pool', 'milestone' => 'One length'])
            ->tasks()->create(['position' => 1, 'description' => 'Swim lesson']);

        // Solo: their own week against the line, with a way into a league.
        $this->actingAs($user)->get(route('matchups'))->assertOk()
            ->assertSee('Go solo against the line')
            ->assertSee('This week against the line')
            ->assertSee('Learn to swim');

        // Finished season: a link to History.
        $past = League::factory()->create(['commissioner_id' => $user->id, 'name' => 'Spring Swim', 'starts_on' => now()->subWeeks(6)->startOfWeek(), 'ends_on' => now()->subWeeks(2)->endOfWeek()]);
        $past->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        $this->actingAs($user)->get(route('matchups'))->assertOk()
            ->assertSee('Season over')
            ->assertSee('Spring Swim is in the books.')
            ->assertSee(route('dashboard', ['tab' => 'history']), false);

        // A season about to kick off.
        $next = League::factory()->create(['commissioner_id' => $user->id, 'name' => 'Summer Swim', 'starts_on' => now()->addWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(6)->endOfWeek()]);
        $next->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        $this->actingAs($user)->get(route('matchups'))->assertOk()
            ->assertSee('Season not started')
            ->assertSee('Summer Swim kicks off')
            ->assertSee('Invite friends');
    }

    public function test_starting_a_goalleague_or_challenge_is_a_big_obvious_choice(): void
    {
        $user = User::factory()->create();

        // No league yet: a big friendly call to action, and the sheet with both choices explained.
        $this->actingAs($user)->get(route('leagues.index'))->assertOk()
            ->assertSee('data-open-sheet="start-sheet"', false)
            ->assertSee('Play your goal with friends.')
            ->assertSeeInOrder(['What do you want to start?', 'Start a GoalLeague', 'A season with friends', 'Start a challenge', 'A short sprint together'])
            ->assertSee(route('leagues.create'))->assertSee(route('challenges.create'));

        // With a league: the list comes first, then GoalLeague and challenge side by side, on GoalLeagues and the Clubhouse.
        $league = League::factory()->create(['name' => 'Morning Crew', 'commissioner_id' => $user->id]);
        $league->members()->create(['user_id' => $user->id]);

        foreach ([route('leagues.index'), route('dashboard')] as $page) {
            $this->actingAs($user)->get($page)->assertSeeInOrder(['Morning Crew', 'New GoalLeague', 'New challenge', 'Join with a code']);
        }
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

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('leagues.index'), '/').'"[^>]*aria-current="page"/', $response->getContent());
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

    public function test_the_five_main_tabs_have_no_back_arrow(): void
    {
        $user = User::factory()->create();

        foreach (['dashboard', 'leagues.index', 'matchups', 'highlights.index', 'profile.show'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()->assertDontSee('data-back', false)->assertDontSee('aria-label="Breadcrumb"', false);
        }
    }

    public function test_every_other_screen_has_a_labelled_back_arrow_to_a_sensible_parent(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['title' => 'Learn to swim']);
        $league = League::factory()->create(['commissioner_id' => $user->id, 'name' => 'Swim Club']);
        $member = $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);

        $cases = [
            [route('leagues.show', $league), route('leagues.index'), 'GoalLeagues'],
            [route('leagues.standings', $league), route('leagues.show', $league), 'Swim Club'],
            [route('leagues.standings.player', [$league, $member]), route('leagues.standings', $league), 'Standings'],
            [route('leagues.feed', $league), route('leagues.show', $league), 'Swim Club'],
            [route('goals.show', $goal), route('dashboard'), 'Clubhouse'],
            [route('profile.edit'), route('profile.show'), 'Profile'],
            [route('friends.index'), route('highlights.index'), 'Highlights'],
            [route('today'), route('dashboard'), 'Clubhouse'],
        ];

        foreach ($cases as [$url, $parent, $label]) {
            $page = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(e($parent), '/').'" data-back[^>]*aria-label="Back to '.preg_quote(e($label), '/').'"/', $page, "Back on {$url}");
        }
    }

    public function test_the_screen_title_sits_next_to_back_and_desktop_gets_breadcrumbs(): void
    {
        $user = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id, 'name' => 'CPA Prep League']);
        $league->members()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('leagues.standings', $league))->assertOk()
            ->assertSee('data-screen-title>Standings<', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'GoalLeagues', 'CPA Prep League', 'aria-current="page">Standings<'], false);
    }

    public function test_the_old_in_hero_back_links_are_gone(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertDontSee('&larr; Profile', false);
        $this->actingAs($user)->get(route('goals.show', $goal))->assertOk()->assertDontSee('back-label', false);
    }
}
