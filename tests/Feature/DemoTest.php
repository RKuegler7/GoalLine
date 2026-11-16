<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\GoalWeekResult;
use App\Jobs\GenerateRoadmap;
use App\Jobs\RefinePlan;
use App\MatchupOutcome;
use App\Models\Challenge;
use App\Models\Dispute;
use App\Models\GoalIntake;
use App\Models\GroupTask;
use App\Models\Highlight;
use App\Models\League;
use App\Models\PlanRefinement;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\Nudged;
use App\Services\AiBudget;
use App\Services\DemoCoach;
use App\Services\DemoWorld;
use App\Services\PlanEditor;
use App\Services\RoadmapPlanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
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
        // Ryan, his five league mates and friends, and four made-up people for Explore.
        $this->assertSame(10, User::where('demo_sandbox', $visitor->demo_sandbox)->count());
        $this->assertEqualsCanonicalizing(['Marathon by March', 'SIE Exam Study Prep', 'SIE Exam Study Prep · Season 1'], $visitor->leagues()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Read 10 pages a day', 'Morning and Evening Prayer', 'Lent 2027'], $visitor->challenges()->pluck('title')->all());
        $this->assertCount(4, $visitor->friends());
        $this->assertSame(4, $visitor->notifications()->count());

        $this->get(route('dashboard'))->assertOk()->assertSee('Demo mode')->assertSee('Reset demo')->assertSee('Run the DC Marathon by March')->assertSee('Pass the SIE exam');
    }

    public function test_every_main_page_works_in_the_demo_with_live_matchups(): void
    {
        $this->get(route('demo'));
        $visitor = auth()->user();
        $running = League::where('name', 'Marathon by March')->whereHas('members', fn ($members) => $members->where('user_id', $visitor->id))->sole();
        $study = League::where('name', 'SIE Exam Study Prep')->whereHas('members', fn ($members) => $members->where('user_id', $visitor->id))->sole();

        $this->get(route('leagues.show', $running))->assertOk()->assertSee('GoalWeek 6 of 20')->assertSee('GoalWeek 6 Matchup')->assertSee('Group tasks')->assertSee('10-minute foam roll after the long run');
        $this->get(route('leagues.show', $study))->assertOk()->assertSee('Manage invites and members'); // The visitor runs this one.
        $this->assertSame(5, $running->fresh()->results->pluck('week')->unique()->count()); // Weeks 1 to 5 scored.

        foreach ([route('leagues.index'), route('leagues.feed', $running), route('highlights.index'), route('friends.index'), route('inbox'),
            route('profile.show'), route('challenges.show', $visitor->challenges()->first()), route('goals.show', $visitor->goals()->first())] as $page) {
            $this->get($page)->assertOk();
        }

        $this->get(route('leagues.matchups', $running))->assertOk()->assertSee('Your Matchup')->assertSee('Scoreboard');
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

    public function test_last_week_shows_a_smashed_week_with_proof_and_one_that_needs_proof(): void
    {
        $this->get(route('demo'));
        $lastWeek = fn (string $goal) => RoadmapWeek::whereHas('goal', fn ($goals) => $goals->where('title', $goal))
            ->whereDate('starts_on', '2026-10-19')->sole();

        $peter = $lastWeek('Finish a spring marathon');
        $this->assertSame(GoalWeekResult::Smashed, $peter->result());
        $this->assertTrue($peter->tasks->contains(fn ($task) => $task->is_bonus && $task->isVerified()));

        $steph = $lastWeek('Pass the SIE before spring');
        $this->assertTrue($steph->needsProofForSmashed());
        $this->assertSame(GoalWeekResult::Past, $steph->result());
    }

    public function test_the_demo_seasons_show_wins_photo_finishes_and_the_capped_credit(): void
    {
        $this->get(route('demo'));
        $result = fn (string $league, int $week, string $name) => League::where('name', $league)->sole()->results()
            ->where('week', $week)->whereHas('user', fn ($users) => $users->where('name', $name))->sole();
        $row = fn ($result) => [$result->result->value, $result->outcome, $result->result_points, $result->matchup_points];

        // A higher band wins outright: Peter's proven Smashed week, 3 line + 2 match.
        $this->assertSame(['smashed', MatchupOutcome::Win, 3, 2], $row($result('Marathon by March', 5, 'Peter Kilpatrick')));
        // Both In line at exactly 100%: a photo-finish tie, 1 each.
        $this->assertSame(['in_line', MatchupOutcome::Tie, 1, 1], $row($result('Marathon by March', 2, 'Peter Kilpatrick')));
        $this->assertSame(['in_line', MatchupOutcome::Tie, 1, 1], $row($result('Marathon by March', 2, 'Steph Busch')));
        // Both In line, 100% beats 90%: a photo finish win for 1 matchup star, and 0 for the loss.
        $this->assertSame(['in_line', MatchupOutcome::CloseWin, 1, 1], $row($result('SIE Exam Study Prep', 1, 'Andrew Abela')));
        $this->assertSame(['in_line', MatchupOutcome::CloseLoss, 1, 0], $row($result('SIE Exam Study Prep', 1, 'Jack Yoest')));
        // Steph's unproven 150%+ SIE week is credited as Past at 149%.
        $steph = RoadmapWeek::whereHas('goal', fn ($goals) => $goals->where('title', 'Pass the SIE before spring'))->whereDate('starts_on', '2026-10-19')->sole();
        $this->assertSame(149, $steph->creditedPercent());
        $this->assertSame('past', $result('SIE Exam Study Prep', 5, 'Steph Busch')->result->value);

        $this->get(route('leagues.matchups', [League::where('name', 'SIE Exam Study Prep')->sole(), 1]))->assertOk()
            ->assertSee('Photo finish: both In line. Andrew Abela takes the photo finish, 100% to 90%.');
    }

    public function test_the_seeder_builds_one_demo_world_with_a_known_sign_in_and_can_run_again(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(10, User::where('demo_sandbox', DemoWorld::SEEDED_SANDBOX)->count());
        $this->assertSame(1, User::where('email', 'test@example.com')->count());
        $this->assertSame(6, League::count());

        $this->post('/login', ['email' => DemoSeeder::EMAIL, 'password' => DemoSeeder::PASSWORD])->assertRedirect();
        $this->assertSame(DemoWorld::VISITOR_NAME, auth()->user()->name);
    }

    public function test_the_demo_still_opens_when_photo_storage_is_down(): void
    {
        // A disk whose root is a file, set to throw: every write fails, like S3 before it's set up.
        $root = tempnam(sys_get_temp_dir(), 'not-a-folder');
        config(['filesystems.disks.uploads' => ['driver' => 'local', 'root' => $root, 'throw' => true]]);
        Storage::forgetDisk('uploads');

        $this->get(route('demo'))->assertRedirect(route('dashboard'));
        $this->assertTrue(auth()->user()->isDemo());
        $this->get(route('dashboard'))->assertOk();

        @unlink($root);
    }

    public function test_cleaning_up_old_demos_never_blocks_a_new_one_when_photo_storage_is_down(): void
    {
        $this->get(route('demo'));
        $old = auth()->user()->demo_sandbox;
        $this->post(route('logout'));

        $root = tempnam(sys_get_temp_dir(), 'not-a-folder');
        config(['filesystems.disks.uploads' => ['driver' => 'local', 'root' => $root, 'throw' => true]]);
        Storage::forgetDisk('uploads');
        $this->travel(25)->hours();

        $this->get(route('demo'))->assertRedirect(route('dashboard'));
        $this->assertSame(0, User::where('demo_sandbox', $old)->count());

        @unlink($root);
    }

    public function test_a_demo_that_cant_be_built_shows_a_friendly_note_not_an_error(): void
    {
        $this->mock(DemoWorld::class, fn (MockInterface $mock) => $mock->shouldReceive('create')->andThrow(new RuntimeException('Database is down.')));

        $this->get(route('demo'))->assertRedirect(route('login'))->assertSessionHas('status', 'The demo couldn\'t start just now. Please try again in a minute.');
        $this->assertGuest();
    }

    public function test_judges_find_the_demo_on_the_login_page_and_never_type_a_password(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Try the demo')->assertSee(route('demo'));
        $this->get(route('demo'))->assertRedirect(route('dashboard'));
        $this->assertTrue(auth()->user()->isDemo());
    }

    public function test_the_demo_shows_plan_honesty_and_a_dispute(): void
    {
        $this->get(route('demo'));
        $visitor = auth()->user();
        $sandbox = $visitor->demo_sandbox;

        $this->assertSame(2, GoalIntake::whereHas('goal', fn ($goals) => $goals->where('user_id', $visitor->id))->where('status', GoalIntake::ANSWERED)->count());
        $this->assertTrue(RoadmapWeek::whereHas('goal.user', fn ($users) => $users->where('demo_sandbox', $sandbox))->whereNull('floor_points')->doesntExist(), 'Every demo plan has its minimum pace.');
        $this->assertTrue(RoadmapWeek::whereHas('goal', fn ($goals) => $goals->where('user_id', $visitor->id))->whereNotNull('floor_note')->exists(), 'A plan-floor note.');
        $this->assertTrue(RoadmapWeek::whereHas('goal.user', fn ($users) => $users->where('demo_sandbox', $sandbox))->where('lighter_reason', 'Grading week')->exists(), 'A lighter week.');
        $this->assertSame(1, Dispute::whereHas('task.week.goal', fn ($goals) => $goals->where('user_id', $visitor->id))->where('status', Dispute::OPEN)->count(), 'A disputed check-in.');

        $study = $visitor->leagues()->where('name', 'SIE Exam Study Prep')->sole();
        $this->get(route('leagues.matchups', $study))->assertOk()->assertSee('Lighter week');
        $this->get(route('goals.show', $visitor->goals()->where('title', 'Pass the SIE exam')->sole()))
            ->assertSee('We raised this week to the minimum pace your deadline needs')->assertSee('Your plan is built from');
    }

    public function test_on_a_monday_morning_early_birds_have_checked_in_so_the_matchup_is_never_empty(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 09:30', config('app.timezone'))); // A Monday.
        $this->get(route('demo'));
        $visitor = auth()->user();
        $sandbox = $visitor->demo_sandbox;
        $doneToday = fn (User $person) => $person->goals()->first()->weeks()->whereDate('starts_on', '2026-11-09')->sole()
            ->tasks()->whereDate('completed_at', '2026-11-09')->count();

        $peter = User::where('demo_sandbox', $sandbox)->where('name', 'Peter Kilpatrick')->sole();
        $this->assertSame(1, $doneToday($peter), 'Peter ran first thing this morning.');
        $this->assertSame(0, $doneToday($visitor), 'The visitor still has today to do.');

        $running = $visitor->leagues()->where('name', 'Marathon by March')->sole();
        $this->get(route('leagues.matchups', $running))->assertOk()->assertSee('Dispute');
    }

    public function test_at_midnight_nobody_has_checked_in_today_yet(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 00:10', config('app.timezone')));
        $this->get(route('demo'));

        $this->assertTrue(User::where('demo_sandbox', auth()->user()->demo_sandbox)->get()
            ->every(fn (User $person) => $person->goals()->get()->every(fn ($goal) => $goal->weeks()->whereDate('starts_on', '2026-11-09')->first()?->tasks()->whereNotNull('completed_at')->doesntExist() ?? true)));
    }

    public function test_the_demo_coach_uses_saved_samples_and_never_calls_the_api(): void
    {
        // A stand-in client with no expectations: any real Claude call would fail this test.
        config(['services.anthropic.key' => 'test-key']);
        $this->app->bind(Client::class, fn () => Mockery::mock(Client::class));
        $this->get(route('demo'));
        $goal = auth()->user()->goals()->where('title', 'Run the DC Marathon by March')->sole();

        $this->post(route('goals.refinements.store', $goal), ['instruction' => 'Make next week lighter, I have finals.']);
        $refinement = PlanRefinement::sole();
        (new RefinePlan($refinement))->handle(app(RoadmapPlanner::class), app(PlanEditor::class));
        $this->assertSame(PlanRefinement::READY, $refinement->fresh()->status);
        $this->get(route('goals.refinements.show', [$goal, $refinement]))->assertSee('saved sample: GoalWeek')->assertSee('Accept and update my plan');

        $this->post(route('goals.store'), ['title' => 'Learn to juggle', 'category' => 'personal', 'target_date' => today()->addWeeks(6)->toDateString()]);
        $new = auth()->user()->goals()->where('title', 'Learn to juggle')->sole();
        $this->assertSame(GoalIntake::SOURCE_FALLBACK, $new->intake->source, 'Built-in questions right away, no waiting.');

        (new GenerateRoadmap($new))->handle(app(RoadmapPlanner::class));
        $this->assertSame(DemoCoach::PLAN_SUMMARY, $new->fresh()->roadmap_summary);
    }

    public function test_reset_rebuilds_this_visitors_world_and_leaves_others_alone(): void
    {
        $this->get(route('demo'));
        $other = auth()->user()->demo_sandbox;
        $this->post(route('logout'));

        $this->get(route('demo'));
        $first = auth()->user()->demo_sandbox;

        $this->post(route('demo.reset'))->assertRedirect(route('dashboard'))->assertSessionHas('status', 'Your demo is reset: a fresh copy of the same world.');

        $this->assertNotSame($first, auth()->user()->demo_sandbox);
        $this->assertSame(0, User::where('demo_sandbox', $first)->count());
        $this->assertSame(10, User::where('demo_sandbox', $other)->count());

        $real = User::factory()->create();
        $this->actingAs($real)->post(route('demo.reset'))->assertNotFound();
    }

    public function test_expired_demos_are_cleaned_up_when_the_next_one_starts_without_cron(): void
    {
        $this->get(route('demo'));
        $old = auth()->user()->demo_sandbox;
        $this->post(route('logout'));

        $this->travel(25)->hours();
        $this->get(route('demo'));

        $this->assertSame(0, User::where('demo_sandbox', $old)->count());
        $this->assertSame(10, User::whereNotNull('demo_sandbox')->count());
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
        $this->get($real->profileUrl())->assertNotFound(); // Another world: not even the header.

        $study = $visitor->leagues()->where('name', 'SIE Exam Study Prep')->sole();
        $this->post(route('leagues.invites.store', $study), ['who' => 'friend@example.com'])->assertSessionHasErrorsIn('invite', 'who');
        $this->post(route('leagues.invites.store', $study), ['who' => '@real.person'])->assertSessionHasErrorsIn('invite', 'who');

        // No email or push, ever, and the email can't be changed to a real one.
        $this->assertSame(['database'], (new Nudged(User::where('demo_sandbox', $visitor->demo_sandbox)->whereKeyNot($visitor->id)->first()))->via($visitor));
        $this->put(route('profile.update'), ['name' => $visitor->name, 'username' => $visitor->username, 'email' => 'real-inbox@example.com']);
        $this->assertStringEndsWith('@demo.goalline.invalid', $visitor->fresh()->email);

        // And real members can't see into a demo.
        $this->actingAs($real)->get(User::where('demo_sandbox', $visitor->demo_sandbox)->where('name', 'Peter Kilpatrick')->sole()->profileUrl())->assertNotFound();
        $this->actingAs($real)->get(route('people.search', ['q' => 'kilpatrick']))->assertDontSee('Peter Kilpatrick');
        // A friendly "not found" that reveals nothing about the demo league.
        $this->actingAs($real)->get(route('leagues.join', $study->invite_code))->assertOk()->assertSee('find that invite')->assertDontSee($study->name);
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
        // A second visitor (another browser, no demo cookie) gets their own world.
        auth()->logout();
        $this->get(route('demo'));
        $this->assertNotSame($first, auth()->user()->demo_sandbox);
        $this->assertSame(20, User::whereNotNull('demo_sandbox')->count());

        $this->travel(25)->hours();
        $this->assertSame(20, DemoWorld::pruneExpired());
        $this->assertSame(0, User::whereNotNull('demo_sandbox')->count());
        $this->assertSame(0, League::count());
        $this->assertSame([], Storage::disk('uploads')->allFiles('highlights'));
        $this->assertSame([], Storage::disk('uploads')->allFiles('proofs'));
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

    public function test_the_demo_challenges_read_pray_and_wait_for_lent(): void
    {
        $this->get(route('demo'));
        $visitor = auth()->user();
        $challenge = fn (string $title) => $visitor->challenges()->where('title', $title)->sole();

        $read = $challenge('Read 10 pages a day');
        $this->assertSame([Challenge::TARGET, 10, 'pages'], [$read->type, $read->rules['threshold'], $read->rules['unit']]);
        $this->assertTrue($read->isActive());

        $prayer = $challenge('Morning and Evening Prayer');
        $this->assertSame(['Morning Prayer', 'Evening Prayer'], $prayer->checklist());
        $this->assertSame(365, $prayer->lengthInDays());
        $this->assertTrue($prayer->isActive());

        // Lent 2027: Ash Wednesday to Holy Saturday, Sundays off, locked until it starts, what to give up still TBD.
        $lent = $challenge('Lent 2027');
        $this->assertSame(['2027-02-10', '2027-03-27'], [$lent->starts_on->toDateString(), $lent->ends_on->toDateString()]);
        $this->assertTrue($lent->sundays_off);
        $this->assertFalse($lent->hasStarted());
        $this->assertSame('TBD', $lent->rules['label']);
        $this->assertSame(6, $lent->members()->count());
        $this->post(route('challenges.check-in', $lent))->assertForbidden();
        $this->get(route('challenges.show', $lent))->assertOk()->assertSee('Starts Wednesday, Feb 10');

        // The visitor's today is still to do in the daily ones.
        $this->assertFalse($read->fresh()->load('checkIns')->isCheckedIn($visitor, Challenge::today()));
    }

    public function test_the_demo_has_a_finished_season_with_a_finale_to_preview(): void
    {
        $this->get(route('demo'));

        $this->get(route('dashboard', ['tab' => 'history']))->assertOk()->assertSee('SIE Exam Study Prep · Season 1')->assertSee('Preview season finale');
        $this->get(route('help'))->assertSee('Preview season finale');

        $response = $this->get(route('finale.preview'));
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('You did it.')->assertSee('Verified')->assertSee('Scored an 84 on the full practice exam')
            ->assertSee('Goal Day news')->assertSee('Every chapter reviewed and outlined')
            ->assertSee('data-medal-shelf', false);

        // The live season stays at Week 6.
        $this->assertSame(6, auth()->user()->leagues()->where('name', 'Marathon by March')->sole()->currentWeek());
    }
}
