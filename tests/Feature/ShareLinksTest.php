<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Challenge;
use App\Models\Goal;
use App\Models\Invite;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\User;
use App\ShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShareLinksTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_URL = 'https://goalline.example';

    private User $owner;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['app.url' => self::PUBLIC_URL]);
        $this->travelTo(Carbon::parse('2026-10-28 12:00', config('app.timezone')));
        $this->owner = User::factory()->create(['name' => 'Priya Shah']);
        $this->league = League::factory()->create(['name' => 'CPA Prep League', 'category' => GoalCategory::Academic, 'commissioner_id' => $this->owner->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-27']);
        $this->league->members()->create(['user_id' => $this->owner->id, 'goal_id' => Goal::factory()->for($this->owner)->create(['target_date' => '2026-12-31'])->id]);
    }

    public function test_the_copied_invite_link_is_absolute_from_app_url_and_never_an_id(): void
    {
        $invite = $this->league->shareableInvite();
        $url = $invite->url();

        $this->assertSame(self::PUBLIC_URL.'/invite/'.$invite->token, $url);
        $this->assertStringNotContainsString('/'.$invite->id, parse_url($url, PHP_URL_PATH));

        // The page copies from a field holding that full link, and the share sheet message carries it too.
        $this->actingAs($this->owner)->get(route('leagues.show', $this->league))
            ->assertSee('value="'.$url.'"', false)
            ->assertSee('data-copy-from="invite-link"', false)
            ->assertSee('data-share-message="'.e(ShareLink::leagueMessage('CPA Prep League', $url)).'"', false)
            ->assertSee('Join my GoalLine league, CPA Prep League: '.$url)
            ->assertDontSee('data-copy="', false);
    }

    public function test_links_are_https_in_production_even_if_app_url_says_http(): void
    {
        config(['app.url' => 'http://goalline.example']);
        $this->app['env'] = 'production';

        $this->assertSame('https://goalline.example/invite/abc', ShareLink::to('/invite/abc'));
    }

    public function test_invite_tokens_are_long_random_and_unique(): void
    {
        $tokens = collect(range(1, 50))->map(fn () => $this->league->invites()->create(['kind' => LeagueInvite::LINK])->token);

        $this->assertCount(50, $tokens->unique());
        $tokens->each(fn (string $token) => $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $token));

        // A sequential id never opens an invite.
        $this->get('/invite/'.$this->league->invites()->first()->id)->assertNotFound();
        $this->get('/invite/1')->assertNotFound();
    }

    public function test_the_join_page_has_a_friendly_summary_and_link_previews(): void
    {
        $invite = $this->league->shareableInvite();

        $this->get($invite->url())->assertOk()
            ->assertSee('Priya invited you')
            ->assertSee('CPA Prep League')
            ->assertSee('Academic')
            ->assertSee('1 member')
            ->assertSee('<meta property="og:title" content="Join CPA Prep League on GoalLine">', false)
            ->assertSee('<meta property="og:url" content="'.$invite->url().'">', false)
            ->assertSee('<meta property="og:image" content="'.self::PUBLIC_URL.'/icons/icon-512.png">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('Priya invited you to a GoalLeague: Academic goals · 1 member · 8-week season.', false);
    }

    public function test_revoked_and_expired_links_say_so_and_cannot_join(): void
    {
        $sam = User::factory()->create();
        $goal = Goal::factory()->for($sam)->create(['target_date' => '2026-12-31']);

        $revoked = $this->league->invites()->create(['kind' => LeagueInvite::LINK]);
        $revoked->forceFill(['revoked_at' => now()])->save();
        $this->get($revoked->url())->assertOk()->assertSee('This invite has expired or was turned off.');
        $this->actingAs($sam)->post(route('leagues.invite.join', $revoked->token), ['goal_id' => $goal->id])->assertStatus(410);

        $expired = $this->league->invites()->create(['kind' => LeagueInvite::LINK, 'expires_at' => now()->subMinute()]);
        $this->actingAs($sam)->get($expired->url())->assertSee('This invite has expired or was turned off.');
        $this->actingAs($sam)->post(route('leagues.invite.join', $expired->token), ['goal_id' => $goal->id])->assertStatus(410);

        $this->assertNull($this->league->memberFor($sam));
    }

    public function test_joining_through_the_copied_link_works(): void
    {
        $sam = User::factory()->create();
        $goal = Goal::factory()->for($sam)->create(['target_date' => '2026-12-31']);
        $invite = $this->league->shareableInvite();

        $this->actingAs($sam)->get($invite->url())->assertOk();
        $this->actingAs($sam)->post(route('leagues.invite.join', $invite->token), ['goal_id' => $goal->id])->assertRedirect(route('leagues.show', $this->league));

        $this->assertNotNull($this->league->memberFor($sam));
    }

    public function test_every_other_copied_link_is_absolute_too(): void
    {
        // Beta sign-up links on the admin page: the full link, never the old "invite-7" element id.
        config(['goalline.admin_emails' => ['admin@goalline.app']]);
        $admin = User::factory()->create(['email' => 'admin@goalline.app']);
        $beta = Invite::create(['max_uses' => 1]);
        $this->assertSame(self::PUBLIC_URL.'/register?invite='.$beta->code, $beta->signupUrl());
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()
            ->assertSee('value="'.e($beta->signupUrl()).'"', false)
            ->assertDontSee('data-copy="invite-', false);

        // Challenge links.
        $challenge = Challenge::factory()->for($this->owner, 'creator')->create();
        $challenge->members()->create(['user_id' => $this->owner->id]);
        $this->assertSame(self::PUBLIC_URL.'/challenges/join/'.$challenge->invite_code, $challenge->joinUrl());
        $this->actingAs($this->owner)->get(route('challenges.show', $challenge))->assertSee('value="'.$challenge->joinUrl().'"', false);

        // The private calendar feed.
        $this->assertStringStartsWith(self::PUBLIC_URL.'/calendar/', $this->owner->calendarUrl());
        $this->actingAs($this->owner)->get(route('settings.notifications'))->assertSee('value="'.$this->owner->calendarUrl().'"', false);
    }
}
