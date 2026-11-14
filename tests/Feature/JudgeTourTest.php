<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Services\DemoWorld;
use App\Services\JudgeTour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class JudgeTourTest extends TestCase
{
    use RefreshDatabase;

    private function demoUser(string $sandbox = 'world-1'): User
    {
        return User::factory()->create(['demo_sandbox' => $sandbox]);
    }

    public function test_only_demo_accounts_see_the_tour(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
            ->assertDontSee('data-judge-tour', false);

        $this->actingAs($this->demoUser())->get(route('dashboard'))->assertOk()
            ->assertSee('data-judge-tour', false)
            ->assertSee('0 of 6')
            ->assertSeeInOrder(array_column(JudgeTour::ITEMS, 'title'))
            ->assertSee('Take me there');
    }

    public function test_the_tour_endpoints_are_404_outside_a_demo(): void
    {
        $this->get(route('judge-tour.go', 'matchup'))->assertRedirect(route('login'));

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('judge-tour.go', 'matchup'))->assertNotFound();
        $this->actingAs($member)->post(route('judge-tour.seen', 'ai_plan'))->assertNotFound();
        $this->actingAs($member)->post(route('judge-tour.hide'))->assertNotFound();
    }

    public function test_items_tick_themselves_off_when_done(): void
    {
        $demo = $this->demoUser();
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $demo->id]);

        $this->actingAs($demo)->get(route('matchups'))->assertOk();
        $this->actingAs($demo)->get(route('leagues.standings', $league))->assertOk();
        $this->assertSame(['matchup', 'standings'], JudgeTour::done($demo));

        // Only the AI panel pings in; other items can't be ticked off by a ping.
        $this->actingAs($demo)->postJson(route('judge-tour.seen', 'ai_plan'))->assertOk()->assertJson(['done' => 3, 'of' => 6]);
        $this->actingAs($demo)->postJson(route('judge-tour.seen', 'finale'))->assertNotFound();

        $this->actingAs($demo)->get(route('dashboard'))->assertSee('3 of 6')->assertSee('data-done', false)->assertSee('Go again');
    }

    public function test_a_failed_request_ticks_nothing_and_members_are_never_tracked(): void
    {
        $demo = $this->demoUser();
        $someoneElses = RoadmapTask::factory()->create();

        $this->actingAs($demo)->post(route('roadmap.tasks.check', $someoneElses), ['intent' => 'done'])->assertForbidden();
        $this->assertSame([], JudgeTour::done($demo));

        $member = User::factory()->create();
        JudgeTour::mark($member, 'matchup');
        $this->assertSame([], JudgeTour::done($member));
    }

    public function test_every_tracked_route_and_destination_exists(): void
    {
        foreach (array_keys(JudgeTour::ROUTES) as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
        $this->assertEqualsCanonicalizing(array_keys(JudgeTour::ITEMS), array_values(array_unique([...array_values(JudgeTour::ROUTES), ...JudgeTour::PINGS])), 'Every item can be ticked off.');

        $demo = $this->demoUser();
        $goal = Goal::factory()->for($demo)->create();
        $this->actingAs($demo)->get(route('judge-tour.go', 'ai_plan'))->assertRedirect(route('goals.show', [$goal, 'tour' => 'ai_plan']));
        $this->actingAs($demo)->get(route('judge-tour.go', 'matchup'))->assertRedirect(route('matchups'));
        $this->actingAs($demo)->get(route('judge-tour.go', 'nope'))->assertNotFound();
    }

    public function test_hiding_is_remembered_and_reset_demo_starts_fresh(): void
    {
        $demo = $this->demoUser();
        JudgeTour::mark($demo, 'matchup');

        $this->actingAs($demo)->post(route('judge-tour.hide'))->assertRedirect();
        $this->actingAs($demo)->get(route('dashboard'))->assertDontSee('data-judge-tour', false);

        // Reset demo removes the world (and its tour); the fresh copy is a new sandbox.
        DemoWorld::remove($demo);
        $fresh = $this->demoUser('world-2');
        $this->assertSame([], JudgeTour::done($fresh));
        $this->assertFalse(JudgeTour::hidden($fresh));
        $this->assertSame([], JudgeTour::done($demo));
    }

    public function test_the_goal_page_ai_panel_reports_when_it_is_opened(): void
    {
        $demo = $this->demoUser();
        $goal = Goal::factory()->for($demo)->create();
        RoadmapWeek::factory()->for($goal)->create();

        $this->actingAs($demo)->get(route('goals.show', $goal))->assertOk()->assertSee('data-judge-tour-ping="ai_plan"', false);
    }
}
