<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Challenge;
use App\Models\League;
use App\Models\User;
use App\Models\UserMute;
use App\Services\PublicListings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Public GoalLeagues and Challenges in the GoalLeagues tab: one listing for Explore and the tab, only what owners made
 * Public, never private data, caps, filters, joining, demo isolation and rate limits.
 */
class PublicListingsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-14 10:00', 'America/New_York'));
        $this->viewer = User::factory()->create();
        $this->owner = User::factory()->create(['name' => 'Ana Ruiz', 'email' => 'ana.secret@example.com']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function challenge(array $attributes = []): Challenge
    {
        $challenge = Challenge::factory()->for($this->owner, 'creator')->create($attributes + ['is_public' => true, 'starts_on' => '2026-11-16', 'ends_on' => '2026-12-15']);
        $challenge->members()->create(['user_id' => $this->owner->id]);

        return $challenge;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function league(array $attributes = []): League
    {
        $league = League::factory()->create(['commissioner_id' => $this->owner->id, 'starts_on' => '2026-11-16', 'ends_on' => '2027-01-24'] + $attributes);
        $league->forceFill(['is_public' => $attributes['is_public'] ?? true])->save();
        $league->members()->create(['user_id' => $this->owner->id]);

        return $league;
    }

    public function test_only_public_open_items_from_your_world_appear_and_never_private_details(): void
    {
        $public = $this->challenge(['title' => 'Read 20 pages a day', 'description' => 'Private note: my book list']);
        $this->challenge(['title' => 'Secret sprint', 'is_public' => false]);
        $this->challenge(['title' => 'Old one', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
        $full = $this->challenge(['title' => 'Full house', 'max_members' => 1]);
        $side = $this->challenge(['title' => 'League side', 'league_id' => $this->league(['is_public' => false])->id]);
        $this->league(['name' => 'Spring Triathletes', 'category' => GoalCategory::Fitness]);
        $this->league(['name' => 'Hidden league', 'is_public' => false]);

        $this->assertSame(['Read 20 pages a day'], PublicListings::challenges($this->viewer)->pluck('title')->all());
        $this->assertSame(['Spring Triathletes'], PublicListings::leagues($this->viewer)->pluck('name')->all());

        $page = $this->actingAs($this->viewer)->get(route('leagues.index'))->assertOk()
            ->assertSee('Find a public GoalLeague')->assertSee('Spring Triathletes')->assertSee('Run by Ana Ruiz')
            ->assertSee('Find a public Challenge')->assertSee('Read 20 pages a day')->assertSee('Started by Ana Ruiz')
            ->assertDontSee('Secret sprint')->assertDontSee('Hidden league')->assertDontSee('Full house');
        $page->assertDontSee('ana.secret@example.com')->assertDontSee('my book list');

        $this->actingAs($this->viewer)->get(route('challenges.browse'))->assertOk()->assertSee('Read 20 pages a day')->assertDontSee('ana.secret@example.com');
        $this->assertNotNull($full);
        $this->assertNotNull($side);
        $this->assertNotNull($public);
    }

    public function test_demo_worlds_real_members_and_muted_owners_stay_apart(): void
    {
        $this->challenge(['title' => 'Real one']);
        $demo = User::factory()->create(['demo_sandbox' => 'world-1']);

        $this->assertSame([], PublicListings::challenges($demo)->pluck('title')->all(), 'A demo visitor never sees (or joins) real Challenges.');
        $this->actingAs($demo)->get(route('challenges.join', Challenge::sole()->invite_code))->assertNotFound();

        UserMute::create(['user_id' => $this->viewer->id, 'muted_user_id' => $this->owner->id]);
        $this->assertSame([], PublicListings::challenges($this->viewer)->pluck('title')->all());
    }

    public function test_filters_and_search(): void
    {
        $this->challenge(['title' => 'Steps', 'type' => 'target', 'rules' => ['threshold' => 10000, 'unit' => 'steps', 'per' => 'day', 'max' => 100000]]);
        $this->challenge(['title' => 'No sweets', 'type' => 'abstain', 'rules' => ['label' => 'No sweets'], 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-16']);
        $this->challenge(['title' => 'Year of reading', 'starts_on' => '2026-11-16', 'ends_on' => '2027-11-15']);

        $titles = fn (array $filters) => PublicListings::challenges($this->viewer, $filters, 50)->pluck('title')->sort()->values()->all();
        $this->assertSame(['Steps'], $titles(['kind' => 'target']));
        $this->assertSame(['No sweets'], $titles(['when' => 'underway']));
        $this->assertSame(['No sweets'], $titles(['duration' => 'short']));
        $this->assertSame(['Steps'], $titles(['duration' => 'month']));
        $this->assertSame(['Year of reading'], $titles(['duration' => 'long', 'q' => 'read']));
        $this->assertSame(['No sweets', 'Steps', 'Year of reading'], $titles(['size' => 'small']));
        $this->assertSame([], $titles(['size' => 'large']));

        $this->actingAs($this->viewer)->get(route('challenges.browse', ['kind' => 'abstain']))->assertOk()->assertSee('No sweets')->assertDontSee('Year of reading');
        $this->actingAs($this->viewer)->get(route('challenges.browse', ['kind' => 'nonsense']))->assertSessionHasErrors('kind');
        $this->actingAs($this->viewer)->get(route('leagues.browse', ['when' => 'soon', 'q' => 'x']))->assertOk();
    }

    public function test_joining_from_the_public_lists_uses_the_normal_flows(): void
    {
        $challenge = $this->challenge(['title' => 'Read 20 pages a day']);
        $league = $this->league(['name' => 'Spring Triathletes']);

        // A Challenge: the rules first, then in.
        $this->actingAs($this->viewer)->get(route('challenges.join', $challenge->invite_code))->assertSee('The rules');
        $this->actingAs($this->viewer)->post(route('challenges.join.store', $challenge->invite_code), ['agree' => 1])->assertRedirect(route('challenges.show', $challenge));

        // A GoalLeague: the usual invite link and goal step.
        $this->actingAs($this->viewer)->post(route('explore.join', $league))->assertRedirect($league->fresh()->shareableInvite()->url());

        // Joined ones leave the "Find" rows.
        $this->actingAs($this->viewer)->get(route('leagues.index', ['tab' => 'challenges']))->assertSee('No public Challenges to join right now.');
    }

    public function test_explore_shows_public_challenges_from_the_same_listing(): void
    {
        $this->challenge(['title' => 'Read 20 pages a day']);

        $this->actingAs($this->viewer)->get(route('highlights.explore'))->assertOk()->assertSee('Public Challenges you can join')->assertSee('Read 20 pages a day');
    }

    public function test_the_lists_are_rate_limited(): void
    {
        foreach (range(1, 60) as $ignored) {
            $this->actingAs($this->viewer)->get(route('challenges.browse'))->assertOk();
        }

        $this->actingAs($this->viewer)->get(route('challenges.browse'))->assertStatus(429);
    }
}
