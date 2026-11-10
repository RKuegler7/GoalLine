<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase R: the branded splash, loader and error states, and an empty state with one clear action on every list.
 */
class BrandedStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_splash_shows_on_the_first_page_of_a_visit_only(): void
    {
        $first = $this->get(route('home'))->assertOk();
        $this->assertStringContainsString('data-splash', $first->getContent());

        $second = $this->get(route('about'))->assertOk();
        $this->assertStringNotContainsString('data-splash', $second->getContent());
    }

    public function test_the_splash_never_shows_on_a_background_fetch(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('checkin.sheet'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertStringNotContainsString('data-splash', $response->getContent());
    }

    public function test_the_app_shell_has_the_loader_and_a_try_again_for_slow_or_failed_loads(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // Page-to-page: the line loader over a skeleton, and a friendly error with "Try again".
        $this->assertMatchesRegularExpression('/data-nav-skeleton.*data-nav-loading.*gl-loader.*data-nav-error.*data-load-retry/s', $html);
        // The check-in sheet: a delayed loader while it loads, and the same "Try again" if it can't.
        $this->assertStringContainsString('template data-checkin-loading', $html);
        $this->assertMatchesRegularExpression('/template data-checkin-error.*Try again/s', $html);
        $this->assertStringContainsString('gl-loader--delayed', $html);
        $this->assertStringNotContainsString('Exception', $html);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errorPages(): array
    {
        return [
            'not found' => [404, 'This page is off the field'],
            'forbidden' => [403, 'isn’t yours to open'],
            'expired form' => [419, 'sat a little too long'],
            'too many tries' => [429, 'you’re moving fast'],
            'down for a tune-up' => [503, 'quick tune-up'],
        ];
    }

    #[DataProvider('errorPages')]
    public function test_error_pages_are_branded_and_return_the_right_status(int $status, string $headline): void
    {
        Route::middleware('web')->get('/_test/status/{status}', fn (int $status) => abort($status));

        $this->get('/_test/status/'.$status)
            ->assertStatus($status)
            ->assertSee($headline, false)
            ->assertSee('data-error-page="'.$status.'"', false)
            ->assertSee('Back to GoalLine');
    }

    public function test_a_missing_page_is_a_branded_404(): void
    {
        $this->get('/no-such-page-anywhere')->assertNotFound()->assertSee('This page is off the field')->assertSee('Back to GoalLine');
    }

    public function test_a_server_error_is_friendly_and_shows_no_technical_details_in_production(): void
    {
        Notification::fake();
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('secret-internal-detail at line 42'));

        $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertSee('Something slipped on our end')
            ->assertSee('Try again')
            ->assertDontSee('secret-internal-detail')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('vendor/laravel');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function emptyLists(): array
    {
        return [
            'today, nothing due' => ['today', 'Nothing due today', 'See the week'],
            'friends' => ['friends.index', 'Goals stick better with company', 'Find people'],
            'notifications' => ['inbox', 'All quiet', 'Add friends'],
            'highlights' => ['highlights.index', 'Your Highlights start here', 'Post a highlight'],
            'league invites' => ['leagues.index', 'No invites waiting', 'Join with a code'],
            'goalleagues' => ['leagues.index', 'Play your goal with friends', 'Start a GoalLeague'],
            'dashboard, no goals yet' => ['dashboard', 'Name a goal and its date', 'Set your first goal'],
        ];
    }

    #[DataProvider('emptyLists')]
    public function test_every_empty_list_has_a_process_focused_line_and_one_clear_action(string $route, string $message, string $action): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($route))->assertOk()
            ->assertSee($message, false)
            ->assertSee($action, false);
    }

    public function test_people_search_with_no_results_offers_an_invite(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('people.search', ['q' => 'zzqqxx']))->assertOk()
            ->assertSee('No one found for “zzqqxx”', false)
            ->assertSee('Invite a friend')
            ->getContent();

        $this->assertStringContainsString('data-empty-state', $html);
        $this->assertStringContainsString('data-empty-action', $html);
    }

    public function test_matchups_before_kickoff_say_when_the_season_starts(): void
    {
        $this->travelTo('2026-11-11 10:00');
        $league = $this->leagueOfOne('2026-11-16', '2026-12-13');

        $this->actingAs($league->commissioner)->get(route('leagues.matchups', $league))
            ->assertRedirect(route('leagues.show', $league))
            ->assertSessionHas('status', 'Matchups start at kickoff, Monday, November 16.');
    }

    public function test_a_solo_season_invites_someone_to_play(): void
    {
        $this->travelTo('2026-10-22 10:00');
        $league = $this->leagueOfOne('2026-10-19', '2026-11-15');

        $this->actingAs($league->commissioner)->get(route('leagues.matchups', $league))->assertOk()
            ->assertSee('you against the line this season', false)
            ->assertSee('data-empty-action', false)
            ->assertSee(route('leagues.show', $league).'#invite', false);
    }

    private function leagueOfOne(string $startsOn, string $endsOn): League
    {
        $league = League::factory()->create(['starts_on' => $startsOn, 'ends_on' => $endsOn]);
        $goal = Goal::factory()->for($league->commissioner)->create(['target_date' => '2027-01-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $league->members()->create(['user_id' => $league->commissioner_id, 'goal_id' => $goal->id]);

        return $league;
    }
}
