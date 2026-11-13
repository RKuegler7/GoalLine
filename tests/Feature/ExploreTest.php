<?php

namespace Tests\Feature;

use App\HighlightKind;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\PostVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Explore shows only GoalLeagues their owners listed and posts their authors made public; nothing private ever
 * leaks, a demo world stays separate, reports and mutes hide posts, and it's rate limited.
 */
class ExploreTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author, ?PostVisibility $visibility, string $caption, HighlightKind $kind = HighlightKind::GreatWeek): Highlight
    {
        return $author->highlights()->create(['kind' => $kind, 'visibility' => $visibility, 'headline' => 'Goal', 'caption' => $caption]);
    }

    public function test_explore_shows_only_public_leagues_and_public_posts(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['name' => 'Ana Ruiz']);
        $public = League::factory()->create(['name' => 'Spring Triathletes', 'starts_on' => now()->addWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(10)]);
        $public->forceFill(['is_public' => true])->save();
        League::factory()->create(['name' => 'Secret Crew', 'starts_on' => now()->addWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(10)]);

        $this->makePost($author, PostVisibility::Public, 'Open water swim, everyone!');
        $this->makePost($author, PostVisibility::LeagueFriends, 'Friends only caption');
        $this->makePost($author, PostVisibility::League, 'League only caption', HighlightKind::CheckIn);
        $this->makePost($author, null, 'Circle win caption');

        $this->actingAs($viewer)->get(route('highlights.explore'))->assertOk()
            ->assertSee('Public GoalLeagues you can join')->assertSee('Spring Triathletes')->assertDontSee('Secret Crew')
            ->assertSee('From the community')->assertSee('Open water swim, everyone!')
            ->assertDontSee('Friends only caption')->assertDontSee('League only caption')->assertDontSee('Circle win caption')
            ->assertDontSee($author->email)
            ->assertSee('Friends &amp; leagues', false)->assertSee('Inspiration');
    }

    public function test_changing_a_post_back_to_private_takes_it_out_of_explore_at_once(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->makePost($author, PostVisibility::Public, 'Visible for now');

        $this->actingAs($viewer)->get(route('highlights.explore'))->assertSee('Visible for now');
        $this->actingAs($author)->patch(route('highlights.visibility', $post), ['visibility' => 'circle'])->assertRedirect();
        $this->actingAs($viewer)->get(route('highlights.explore'))->assertDontSee('Visible for now');

        // Only the author can change it, and only to allowed audiences.
        $this->actingAs($viewer)->patch(route('highlights.visibility', $post), ['visibility' => 'public'])->assertForbidden();
        $this->actingAs($author)->patch(route('highlights.visibility', $post), ['visibility' => 'proof'])->assertSessionHasErrors('visibility');
    }

    public function test_public_is_never_preselected_and_says_what_it_means(): void
    {
        $this->assertSame('Public (Explore)', PostVisibility::Public->label());
        $this->assertSame('Anyone on GoalLine can see this post.', PostVisibility::Public->description());

        $user = User::factory()->create();
        $post = $this->makePost($user, PostVisibility::LeagueFriends, 'Mine', HighlightKind::CheckIn);
        $html = $this->actingAs($user)->get(route('highlights.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Public (Explore)', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="public"[^>]*selected/', $html);
    }

    public function test_a_report_hides_the_post_for_the_reporter_and_lists_it_for_admins(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->makePost($author, PostVisibility::Public, 'Questionable post');

        $this->actingAs($viewer)->post(route('highlights.report', $post), ['reason' => 'Spam'])->assertRedirect();
        $this->get(route('highlights.explore'))->assertDontSee('Questionable post');
        $this->actingAs($other)->get(route('highlights.explore'))->assertSee('Questionable post');

        $admin = User::factory()->create(['email' => 'admin@example.test']);
        config(['goalline.admin_emails' => ['admin@example.test']]);
        $this->actingAs($admin)->get(route('admin.index'))->assertSee('Reported posts')->assertSee('Spam');
    }

    public function test_muting_someone_hides_their_posts_until_you_unmute(): void
    {
        $author = User::factory()->create(['profile_visibility' => 'everyone']);
        $viewer = User::factory()->create();
        $this->makePost($author, PostVisibility::Public, 'Loud post');

        $this->actingAs($viewer)->post(route('users.mute', $author))->assertRedirect();
        $this->get(route('highlights.explore'))->assertDontSee('Loud post');
        $this->get($author->profileUrl())->assertSee('Muted');

        $this->delete(route('users.mute', $author))->assertRedirect();
        $this->get(route('highlights.explore'))->assertSee('Loud post');
    }

    public function test_a_demo_world_and_real_members_never_see_each_others_explore(): void
    {
        $real = User::factory()->create();
        $demo = User::factory()->create(['demo_sandbox' => 'world-1']);
        $this->makePost($real, PostVisibility::Public, 'Real public post');
        $this->makePost($demo, PostVisibility::Public, 'Demo public post');

        $this->actingAs($real)->get(route('highlights.explore'))->assertSee('Real public post')->assertDontSee('Demo public post');
        $this->actingAs($demo)->get(route('highlights.explore'))->assertSee('Demo public post')->assertDontSee('Real public post');
    }

    public function test_joining_a_public_league_uses_the_normal_flow_and_owners_list_and_unlist(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $owner->id, 'starts_on' => now()->addWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(10)]);
        $league->members()->create(['user_id' => $owner->id]);

        // Not listed: no way in from Explore.
        $this->actingAs($joiner)->post(route('explore.join', $league))->assertNotFound();

        $this->actingAs($owner)->patch(route('leagues.public', $league), ['is_public' => 1])->assertRedirect();
        $this->assertTrue($league->fresh()->is_public);
        $this->actingAs($joiner)->patch(route('leagues.public', $league), ['is_public' => 0])->assertForbidden();

        $response = $this->actingAs($joiner)->post(route('explore.join', $league));
        $response->assertRedirect($league->fresh()->shareableInvite()->url());

        $this->actingAs($owner)->patch(route('leagues.public', $league), ['is_public' => 0]);
        $this->actingAs($joiner)->get(route('highlights.explore'))->assertDontSee($league->name);
    }

    public function test_inspiration_opens_start_a_goalleague_filled_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('leagues.create', ['name' => 'Marathon Crew', 'category' => 'fitness', 'goal_title' => 'Run a marathon']))
            ->assertOk()->assertSee('value="Marathon Crew"', false)->assertSee('value="Run a marathon"', false);
    }

    public function test_explore_is_rate_limited_and_paged_newest_first(): void
    {
        $author = User::factory()->create();
        foreach (range(1, 14) as $index) {
            $this->makePost($author, PostVisibility::Public, "Public post number {$index}.");
        }
        $viewer = User::factory()->create();

        $first = $this->actingAs($viewer)->get(route('highlights.explore'))->assertSee('Public post number 14.')->assertDontSee('Public post number 2.')->assertSee('Load older posts');
        $oldestShown = Highlight::orderByDesc('id')->skip(11)->first()->id;
        $this->get(route('highlights.explore', ['before' => $oldestShown]))->assertSee('Public post number 2.')->assertDontSee('Public post number 14.');

        $route = collect(app('router')->getRoutes()->getRoutesByName())['highlights.explore'];
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    public function test_the_demo_has_example_public_leagues_and_posts(): void
    {
        $this->get(route('demo'));

        $this->get(route('highlights.explore'))->assertOk()
            ->assertSee('made-up example')->assertSee('Spring Triathletes')->assertSee('Bar Prep Study Hall')
            ->assertSee('First open-water swim of the year');
    }
}
