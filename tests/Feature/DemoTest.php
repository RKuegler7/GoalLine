<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\GroupTask;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\Notifications\Nudged;
use App\Services\AiBudget;
use App\Services\DemoWorld;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('uploads');
        config(['goalline.beta.invite_only' => true]);
        $this->travelTo(Carbon::parse('2026-10-30 10:00', config('app.timezone'))); // A Friday.
    }

    public function test_one_click_signs_a_visitor_into_a_full_demo_world(): void
    {
        $this->get(route('home'))->assertSee('Try the demo')->assertSee(route('demo'));

        $this->get(route('demo'))->assertRedirect(route('dashboard'));
        $visitor = auth()->user();

        $this->assertSame(DemoWorld::VISITOR_NAME, $visitor->name);
        $this->assertTrue($visitor->isDemo());
        $this->assertSame(8, User::where('demo_sandbox', $visitor->demo_sandbox)->count());
        $this->assertEqualsCanonicalizing(['Marathon by March', '4.0 This Fall'], $visitor->leagues()->pluck('name')->all());
        $this->assertSame(1, $visitor->challenges()->count());
        $this->assertCount(4, $visitor->friends());
        $this->assertSame(4, $visitor->notifications()->count());

        $this->get(route('dashboard'))->assertOk()->assertSee("You're in the GoalLine demo", false)->assertSee('Run the DC Marathon by March')->assertSee('Get a 4.0 GPA this fall');
    }

    public function test_every_main_page_works_in_the_demo_with_live_matchups(): void
    {
        $this->get(route('demo'));
        $visitor = auth()->user();
        $running = League::where('name', 'Marathon by March')->whereHas('members', fn ($members) => $members->where('user_id', $visitor->id))->sole();
        $study = League::where('name', '4.0 This Fall')->whereHas('members', fn ($members) => $members->where('user_id', $visitor->id))->sole();

        $this->get(route('leagues.show', $running))->assertOk()->assertSee('week 4 of 20')->assertSee('Group tasks')->assertSee('10-minute foam roll after the long run');
        $this->get(route('leagues.show', $study))->assertOk()->assertSee('Manage invites and members'); // The visitor runs this one.
        $this->assertSame(3, $running->fresh()->results->pluck('week')->unique()->count()); // Weeks 1 to 3 scored.

        foreach ([route('leagues.index'), route('leagues.feed', $running), route('highlights.index'), route('friends.index'), route('inbox'),
            route('profile.show'), route('challenges.show', Challenge::sole()), route('goals.show', $visitor->goals()->first())] as $page) {
            $this->get($page)->assertOk();
        }

        $this->get(route('leagues.feed', $running))->assertSee('Rock Creek Park trail')->assertSee('Verified');
        $this->get(Highlight::whereNotNull('photo_path')->first()->photoUrl())->assertOk();
    }

    public function test_the_visitors_vote_passes_the_group_task(): void
    {
        $this->get(route('demo'));
        $groupTask = GroupTask::sole();

        $this->post(route('group-tasks.vote', $groupTask), ['approve' => 1])->assertRedirect();

        $this->assertSame(GroupTask::PASSED, $groupTask->fresh()->status);
        $this->assertSame(4, $groupTask->tasks()->count());
    }

    public function test_demo_worlds_never_reach_real_members(): void
    {
        $real = User::factory()->create(['name' => 'Real Person', 'username' => 'real.person', 'email' => 'real@example.com']);
        $this->get(route('demo'));
        $visitor = auth()->user();

        $this->get(route('people.search', ['q' => 'real']))->assertDontSee('Real Person');
        $this->get(route('people.search', ['q' => 'real@example.com']))->assertDontSee('Real Person');
        $this->post(route('friends.request', $real))->assertNotFound();
        $this->post(route('friends.store'), ['who' => 'real.person'])->assertSessionHasErrors('who');
        $this->get($real->profileUrl())->assertViewIs('profile.private');

        $study = $visitor->leagues()->where('name', '4.0 This Fall')->sole();
        $this->post(route('leagues.invites.store', $study), ['who' => 'friend@example.com'])->assertSessionHasErrorsIn('invite', 'who');
        $this->post(route('leagues.invites.store', $study), ['who' => '@real.person'])->assertSessionHasErrorsIn('invite', 'who');

        // No email or push, ever, and the email can't be changed to a real one.
        $this->assertSame(['database'], (new Nudged(User::where('demo_sandbox', $visitor->demo_sandbox)->whereKeyNot($visitor->id)->first()))->via($visitor));
        $this->put(route('profile.update'), ['name' => $visitor->name, 'username' => $visitor->username, 'email' => 'real-inbox@example.com']);
        $this->assertStringEndsWith('@demo.goalline.invalid', $visitor->fresh()->email);

        // And real members can't see into a demo.
        $this->actingAs($real)->get(User::where('demo_sandbox', $visitor->demo_sandbox)->where('name', 'Red Dufour')->sole()->profileUrl())->assertViewIs('profile.private');
        $this->actingAs($real)->get(route('people.search', ['q' => 'dufour']))->assertDontSee('Red Dufour');
        $this->actingAs($real)->get(route('leagues.join', $study->invite_code))->assertNotFound();
    }

    public function test_a_signed_in_member_is_not_switched_into_the_demo(): void
    {
        $real = User::factory()->create();

        $this->actingAs($real)->get(route('demo'))->assertRedirect(route('dashboard'))->assertSessionHas('status');
        $this->assertTrue(auth()->user()->is($real));
        $this->assertSame(0, User::whereNotNull('demo_sandbox')->count());
    }

    public function test_starting_over_makes_a_fresh_world_and_old_worlds_are_deleted(): void
    {
        $this->get(route('demo'));
        $first = auth()->user()->demo_sandbox;
        $this->get(route('demo'));
        $this->assertNotSame($first, auth()->user()->demo_sandbox);
        $this->assertSame(16, User::whereNotNull('demo_sandbox')->count());

        $this->travel(49)->hours();
        $this->assertSame(16, DemoWorld::pruneExpired());
        $this->assertSame(0, User::whereNotNull('demo_sandbox')->count());
        $this->assertSame(0, League::count());
        $this->assertSame([], Storage::disk('uploads')->allFiles('highlights'));
    }

    public function test_demo_visitors_get_a_small_ai_allowance(): void
    {
        $this->get(route('demo'));
        $visitor = auth()->user();
        $budget = app(AiBudget::class);

        $this->assertNull($budget->blockedReason($visitor));
        foreach (range(1, 3) as $request) {
            $budget->record($visitor, 'roadmap', 1000, 1000);
        }

        $this->assertStringContainsString('The demo includes a few AI coaching requests', $budget->blockedReason($visitor));
    }
}
