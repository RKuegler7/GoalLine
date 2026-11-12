<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\League;
use App\Models\User;
use App\Notifications\FeedbackReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_is_in_the_top_bar_on_every_main_screen(): void
    {
        $user = User::factory()->create();

        foreach (['dashboard', 'leagues.index', 'matchups', 'highlights.index', 'profile.show'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertSee('data-open-help class="relative grid size-11', false)
                ->assertSee('aria-label="Help"', false)
                ->assertSee('id="help-sheet"', false);
        }
    }

    public function test_help_opens_at_the_answer_that_fits_the_screen(): void
    {
        $user = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id, 'starts_on' => now()->subWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(6)->endOfWeek()]);
        $league->members()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('leagues.standings', $league))->assertSee('data-help-context="table"', false);
        $this->actingAs($user)->get(route('leagues.matchups', $league))->assertSee('data-help-context="reading-matchup"', false);

        $panel = $this->actingAs($user)->get(route('help', ['context' => 'reading-matchup']), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $panel->assertSee('id="help-reading-matchup"', false)
            ->assertSee('data-help-focus', false)
            ->assertDontSee('<html', false);
        $this->assertSame(1, substr_count($panel->getContent(), 'data-help-focus'));

        // An unknown context opens nothing in particular; without scripts /help is a full page.
        $this->actingAs($user)->get(route('help', ['context' => 'nope']))->assertOk()->assertSee('<html', false)->assertDontSee('data-help-focus', false);
    }

    public function test_the_faq_is_rendered_from_its_file_with_numbers_from_the_scoring_code(): void
    {
        $faq = require resource_path('help/faq.php');
        $page = $this->actingAs(User::factory()->create())->get(route('help'))->assertOk();

        foreach ($faq as $group) {
            $page->assertSee($group['title']);
            foreach ($group['items'] as $item) {
                $page->assertSee($item['q']);
            }
        }
        $this->assertSame(['Getting started', 'Your plan', 'Checking in', 'Scoring', 'Fairness', 'Friends and leagues', 'Privacy and safety', 'Account'], array_column($faq, 'title'));

        $bands = collect($faq)->firstWhere('id', 'scoring')['items'][0]['a'];
        $this->assertStringContainsString((int) round(GoalWeekResult::IN_LINE_AT * 100).'%', $bands);
        $this->assertStringContainsString((int) round(GoalWeekResult::SMASHED_AT * 100).'% and up', $bands);
        $page->assertSee('data-help-search', false)->assertSee('Take the tour')->assertSee('How is GoalLine scored?')->assertSee('Report a problem or send feedback');
    }

    public function test_feedback_is_stored_sent_to_the_owner_in_the_app_and_rate_limited(): void
    {
        Notification::fake();
        config(['goalline.admin_emails' => ['owner@example.com']]);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('feedback.store'), ['body' => 'The matchup line is great.', 'page' => '/leagues/1/matchups'])->assertRedirect();

        $this->assertDatabaseHas('feedback', ['user_id' => $member->id, 'page' => '/leagues/1/matchups']);
        Notification::assertSentTo($owner, FeedbackReceived::class);

        foreach (range(2, 10) as $note) {
            $this->actingAs($member)->post(route('feedback.store'), ['body' => "Note {$note}"]);
        }
        $this->actingAs($member)->post(route('feedback.store'), ['body' => 'One too many'])->assertTooManyRequests();
        $this->assertSame(10, DB::table('feedback')->count());
    }

    public function test_the_tour_is_offered_once_to_new_and_demo_accounts_and_can_be_replayed(): void
    {
        $new = User::factory()->create(['onboarded_at' => now(), 'tour_completed_at' => null]);
        $this->actingAs($new)->get(route('dashboard'))->assertSee('data-tour-pending', false)->assertSee('data-tour hidden', false);

        $this->actingAs($new)->postJson(route('tour.done'))->assertOk();
        $this->assertNotNull($new->fresh()->tour_completed_at);
        $this->actingAs($new->fresh())->get(route('dashboard'))->assertDontSee('data-tour-pending', false);
        $this->actingAs($new->fresh())->get(route('help'))->assertSee('data-tour-start', false);

        // Still in the welcome walkthrough: not yet.
        $this->actingAs(User::factory()->create(['onboarded_at' => null, 'tour_completed_at' => null]))->get(route('help'))->assertDontSee('data-tour-pending', false);

        // The demo visitor is offered it on first login, with the Help icon.
        auth()->logout();
        $this->get(route('demo'));
        $this->get(route('dashboard'))->assertSee('data-tour-pending', false)->assertSee('aria-label="Help"', false);
    }
}
