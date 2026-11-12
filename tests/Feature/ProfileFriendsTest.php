<?php

namespace Tests\Feature;

use App\FriendsListVisibility;
use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\Friendship;
use App\Models\League;
use App\Models\User;
use App\Services\LeagueStats;
use App\Services\LeagueTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Profiles: the relationship button's states, the Friends list (in place, paged, searchable, private when asked),
 * mutual friends and leagues, and Stats that match the standings.
 */
class ProfileFriendsTest extends TestCase
{
    use RefreshDatabase;

    private function friends(User $a, User $b): void
    {
        Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id])->forceFill(['status' => Friendship::ACCEPTED])->save();
    }

    public function test_the_relationship_button_goes_add_sent_accept_friends_and_back(): void
    {
        $avery = User::factory()->create(['profile_visibility' => 'everyone']);
        $sam = User::factory()->create(['profile_visibility' => 'everyone']);

        $this->actingAs($avery)->get($sam->profileUrl())->assertOk()->assertSee('Add friend')->assertSee('Friends can see your League + friends posts.');
        $this->post(route('friends.request', $sam))->assertRedirect();
        $this->get($sam->profileUrl())->assertSee('Request sent');

        $friendship = Friendship::sole();
        $this->actingAs($sam)->get($avery->profileUrl())->assertSee('>Accept<', false)->assertSee('Decline');
        $this->post(route('friends.accept', $friendship))->assertRedirect();
        $this->get($avery->profileUrl())->assertSee('Friends')->assertSee('Remove friend');

        $this->delete(route('friends.destroy', $friendship))->assertRedirect();
        $this->assertSame(0, Friendship::count());

        // Cancel a sent request.
        $this->actingAs($avery)->post(route('friends.request', $sam));
        $this->delete(route('friends.destroy', Friendship::sole()))->assertRedirect();
        $this->assertSame(0, Friendship::count());
    }

    public function test_the_friends_list_pages_searches_and_tags_mutual_friends_without_emails(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'everyone']);
        $viewer = User::factory()->create();
        $people = User::factory()->count(25)->sequence(fn ($sequence) => ['name' => sprintf('Runner %02d', $sequence->index)])->create();
        $people->each(fn (User $person) => $this->friends($owner, $person));
        $this->friends($owner, $viewer);
        $this->friends($viewer, $people[0]);

        $this->actingAs($viewer)->get($owner->profileUrl())->assertSee('Friends (26)')->assertSee('1 mutual friend');

        $page = $this->get(route('profiles.friends', ['user' => $owner, 'partial' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $page->assertSee('Runner 00')->assertSee('Mutual')->assertSee('Show more')->assertDontSee($people[0]->email)->assertDontSee('@example', false);
        $this->assertSame(20, substr_count($page->getContent(), 'data-friend-row'));

        $this->get(route('profiles.friends', ['user' => $owner, 'page' => 2, 'partial' => 1]))->assertSee('Runner 24');
        $search = $this->get(route('profiles.friends', ['user' => $owner, 'q' => 'Runner 07']))->assertOk();
        $this->assertSame(1, substr_count($search->getContent(), 'data-friend-row'));
    }

    public function test_who_can_see_my_friends_list_is_respected(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'everyone', 'friends_list_visibility' => FriendsListVisibility::Friends]);
        $friend = User::factory()->create();
        $mate = User::factory()->create();
        $mutual = User::factory()->create();
        $this->friends($owner, $friend);
        $this->friends($owner, $mutual);
        $this->friends($mate, $mutual);
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $owner->id]);
        $league->members()->create(['user_id' => $mate->id]);

        // Friends only: a league mate can't open the list, but sees the mutual count.
        $this->actingAs($friend)->get(route('profiles.friends', $owner))->assertOk();
        $this->actingAs($mate)->get(route('profiles.friends', $owner))->assertForbidden();
        $this->get($owner->profileUrl())->assertSee('1 mutual friend')->assertDontSee('data-friends-expand', false);

        // Friends and league mates (the default) lets the league mate in.
        $owner->forceFill(['friends_list_visibility' => FriendsListVisibility::FriendsAndLeagues])->save();
        $this->get(route('profiles.friends', $owner))->assertOk();

        // Only me.
        $owner->forceFill(['friends_list_visibility' => FriendsListVisibility::OnlyMe])->save();
        $this->actingAs($friend)->get(route('profiles.friends', $owner))->assertForbidden();
        $this->actingAs($owner)->get(route('profiles.friends', $owner))->assertOk();

        // The setting saves from Profile settings.
        $this->actingAs($owner)->get(route('profile.edit'))->assertSee('Who can see my friends list');
    }

    public function test_people_who_cant_be_found_by_name_stay_out_of_others_lists(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'everyone']);
        $hidden = User::factory()->create(['name' => 'Quiet Quinn', 'discoverable' => false]);
        $viewer = User::factory()->create();
        $this->friends($owner, $hidden);
        $this->friends($owner, $viewer);

        $this->actingAs($viewer)->get(route('profiles.friends', $owner))->assertDontSee('Quiet Quinn');
        $this->actingAs($owner)->get(route('profiles.friends', $owner))->assertSee('Quiet Quinn');
    }

    public function test_private_profiles_and_rate_limits(): void
    {
        $private = User::factory()->create(['profile_visibility' => 'private']);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get($private->profileUrl())->assertOk()->assertDontSee('data-profile-friends', false);
        $this->get(route('profiles.friends', $private))->assertForbidden();

        $routes = collect(app('router')->getRoutes()->getRoutesByName());
        $this->assertContains('throttle:60,1', $routes['profiles.show']->gatherMiddleware());
        $this->assertContains('throttle:60,1', $routes['profiles.friends']->gatherMiddleware());
    }

    public function test_profile_stats_match_the_standings_and_are_for_league_mates_only(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 09:00', 'America/New_York'));
        $league = League::factory()->create(['starts_on' => '2026-10-05', 'ends_on' => '2026-11-29']);
        [$alex, $blair] = [User::factory()->create(['profile_visibility' => 'everyone']), User::factory()->create()];
        foreach ([$alex, $blair] as $user) {
            $league->members()->create(['user_id' => $user->id]);
        }
        foreach ([[1, $alex, GoalWeekResult::Past, MatchupOutcome::Win], [1, $blair, GoalWeekResult::InLine, MatchupOutcome::Loss], [2, $alex, GoalWeekResult::Smashed, MatchupOutcome::CloseWin], [2, $blair, GoalWeekResult::Smashed, MatchupOutcome::Loss]] as [$week, $user, $band, $outcome]) {
            $league->results()->create(['user_id' => $user->id, 'week' => $week, 'result' => $band, 'result_points' => $band->points(), 'opponent_id' => ($user->is($alex) ? $blair : $alex)->id, 'vs_median' => false, 'outcome' => $outcome, 'matchup_points' => $outcome->points()]);
        }

        $row = (new LeagueStats(new LeagueTable($league->fresh())))->standings()->first(fn ($row) => $row['member']->user_id === $alex->id);

        $this->actingAs($blair)->get($alex->profileUrl())->assertOk()
            ->assertSee('data-profile-stats', false)
            ->assertSeeInOrder(['Total stars', (string) $row['total'], 'Line stars', (string) $row['resultPoints'], 'Matchup stars', (string) $row['matchupPoints']])
            ->assertSee('2–0–0')->assertSee('GoalLeagues you share');

        // Someone outside the league sees no league stats.
        $this->actingAs(User::factory()->create())->get($alex->profileUrl())->assertDontSee('data-profile-stats', false);
    }

    public function test_the_standings_have_a_big_stats_switch_with_presets_sorting_and_a_reset(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 09:00', 'America/New_York'));
        $league = League::factory()->create(['starts_on' => '2026-10-05', 'ends_on' => '2026-11-29']);
        [$alex, $blair] = [User::factory()->create(['name' => 'Alex']), User::factory()->create(['name' => 'Blair'])];
        foreach ([$alex, $blair] as $user) {
            $league->members()->create(['user_id' => $user->id]);
        }
        foreach ([[$alex, GoalWeekResult::InLine, MatchupOutcome::Loss], [$blair, GoalWeekResult::Past, MatchupOutcome::Win]] as [$user, $band, $outcome]) {
            $league->results()->create(['user_id' => $user->id, 'week' => 1, 'result' => $band, 'result_points' => $band->points(), 'opponent_id' => ($user->is($alex) ? $blair : $alex)->id, 'vs_median' => false, 'outcome' => $outcome, 'matchup_points' => $outcome->points()]);
        }

        $this->actingAs($alex)->get(route('leagues.standings', $league))->assertOk()->assertSee('data-standings-switch', false)->assertSee('Stats');
        $this->get(route('leagues.standings', [$league, 'view' => 'stats']))->assertOk()
            ->assertSee('Matchup ★')->assertSee('W–D–L')->assertSee('Consistency')->assertSee('Performance')
            ->assertSeeInOrder(['Blair', 'Alex']);
        $this->get(route('leagues.standings', [$league, 'view' => 'stats', 'preset' => 'consistency', 'col' => 'in_line']))->assertOk()
            ->assertSeeInOrder(['Alex', 'Blair'])->assertSee('Reset to official order')->assertDontSee('Points');

        // Members only.
        $this->actingAs(User::factory()->create())->get(route('leagues.standings', [$league, 'view' => 'stats']))->assertForbidden();
    }
}
